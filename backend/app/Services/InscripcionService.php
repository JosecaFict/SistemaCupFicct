<?php

namespace App\Services;

use App\Enums\EstadoPostulacion;
use App\Models\Grupo;
use App\Models\Inscripcion;
use App\Models\Postulacion;
use App\Models\Turno;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/*
| InscripcionService
| --------------------------------------------------------------------------
| Logica central del Ciclo 1: confirmar() ejecuta todas las validaciones
| obligatorias (CU9) y genera codigo + asigna grupo (CU10, CU12).
|
| Por que en Laravel (no triggers en PostgreSQL):
|   - Necesitamos componer multiples reglas (pago aprobado + requisitos +
|     cupo en grupo) que viven en tablas distintas y devolver mensajes utiles
|     al usuario; los triggers solo pueden RAISE EXCEPTION genericas.
|   - La asignacion de grupo requiere SELECT FOR UPDATE + UPDATE + INSERT
|     en una sola transaccion; Laravel lo expresa con DB::transaction()
|     ligado a HTTP request (mas legible y testeable).
|
| Pasos de confirmar():
|   1. Verifica que la postulacion exista y no este INSCRITA/ANULADA.
|   2. Verifica que tenga al menos un pago APROBADO.
|   3. Verifica que todos los requisitos obligatorios esten VALIDADO.
|   4. Selecciona el primer grupo ACTIVO del turno con cupo libre
|      (con lockForUpdate para evitar overbooking).
|   5. Genera codigo de postulante.
|   6. Crea la inscripcion, actualiza postulacion y contador del grupo.
|   7. Registra evento en bitacora.
*/
class InscripcionService
{
    /**
     * Confirma la inscripcion de una postulacion en el turno dado.
     *
     * @return Inscripcion La inscripcion recien creada.
     * @throws \DomainException Si alguna regla del CU9 no se cumple.
     */
    public static function confirmar(Postulacion $postulacion, int $turnoId): Inscripcion
    {
        return DB::transaction(function () use ($postulacion, $turnoId) {
            // (1) Estado de la postulacion
            $estadoActual = $postulacion->estado;
            if ($estadoActual === EstadoPostulacion::INSCRITO) {
                throw new \DomainException('La postulacion ya esta inscrita.');
            }
            if ($estadoActual === EstadoPostulacion::ANULADO) {
                throw new \DomainException('La postulacion esta anulada.');
            }

            // (2) Pago aprobado
            if (!$postulacion->tienePagoAprobado()) {
                throw new \DomainException('No se puede confirmar: no existe un pago APROBADO para esta postulacion.');
            }

            // (3) Requisitos obligatorios completos
            if (!RequisitoService::todosCompletados($postulacion)) {
                throw new \DomainException('No se puede confirmar: hay requisitos obligatorios sin validar.');
            }

            // (4) Turno valido y existencia de grupo con cupo
            $turno = Turno::find($turnoId);
            if (!$turno || !$turno->activo) {
                throw new \DomainException('El turno seleccionado no es valido.');
            }

            $grupo = Grupo::where('gestion_cup_id', $postulacion->gestion_cup_id)
                ->where('turno_id', $turno->id)
                ->where('estado', 'ACTIVO')
                ->whereColumn('inscritos_actuales', '<', 'capacidad')
                ->orderBy('codigo')
                ->lockForUpdate()
                ->first();

            if (!$grupo) {
                throw new \DomainException('No hay grupos disponibles en el turno seleccionado.');
            }

            // (5) Codigo de postulante (unico, atomico dentro de la transaccion)
            $codigo = CodigoPostulanteService::generar();

            // (6) Crear inscripcion y actualizar estado/contadores
            $inscripcion = Inscripcion::create([
                'postulacion_id'         => $postulacion->id,
                'grupo_id'               => $grupo->id,
                'turno_id'               => $turno->id,
                'confirmada_por_user_id' => Auth::id(),
                'codigo_postulante'      => $codigo,
                'fecha_inscripcion'      => now(),
            ]);

            $grupo->increment('inscritos_actuales');

            $postulacion->turno_id           = $turno->id;
            $postulacion->grupo_id           = $grupo->id;
            $postulacion->codigo_postulante  = $codigo;
            $postulacion->estado             = EstadoPostulacion::INSCRITO;
            $postulacion->fecha_inscripcion  = now();
            $postulacion->save();

            // (7) Bitacora
            BitacoraService::registrar(
                evento: 'INSCRIPCION_CONFIRMADA',
                entidad: 'inscripcion',
                entidadId: $inscripcion->id,
                datos: [
                    'postulacion_id'    => $postulacion->id,
                    'codigo_postulante' => $codigo,
                    'grupo'             => $grupo->codigo,
                    'turno'             => $turno->codigo,
                ]
            );

            return $inscripcion->fresh(['grupo', 'turno', 'postulacion.persona']);
        });
    }
}
