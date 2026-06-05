<?php

namespace App\Services;

use App\Enums\EstadoNota;
use App\Models\GestionCup;
use App\Models\Nota;
use Illuminate\Support\Carbon;

/*
| NotaService
| --------------------------------------------------------------------------
| Logica reusable para notas:
|   - Calcular si una nota descalifica (valor < nota_minima_aprobacion)
|   - Validar / rechazar un bloque completo (gestion x grupo x materia x examen)
|   - Helpers de busqueda y agregaciones de bloques.
|
| "Bloque" = todas las notas de un grupo en una materia en un examen.
|   Ej: M1 + MAT + Examen 1 = ~40 notas.
*/
class NotaService
{
    /**
     * Determina si una nota descalifica al postulante (nota < nota_minima
     * de la gestion). La regla es: descalifica = true.
     */
    public static function calcularDescalifica(GestionCup $gestion, float $valor): bool
    {
        return $valor < (float) $gestion->nota_minima_aprobacion;
    }

    /**
     * Valida un bloque completo (cambia estado de PENDIENTE a VALIDADA en
     * todas las notas que matchean el filtro). Devuelve cantidad actualizada.
     */
    public static function validarBloque(int $gestionId, int $grupoId, int $gestionMateriaId, int $numeroExamen, int $validadoPorUserId): int
    {
        $postulacionesEnGrupo = \App\Models\Postulacion::where('gestion_cup_id', $gestionId)
            ->where('grupo_id', $grupoId)
            ->pluck('id');

        return Nota::whereIn('postulacion_id', $postulacionesEnGrupo)
            ->where('gestion_materia_id', $gestionMateriaId)
            ->where('numero_examen', $numeroExamen)
            ->where('estado', EstadoNota::PENDIENTE)
            ->update([
                'estado'               => EstadoNota::VALIDADA,
                'validado_por_user_id' => $validadoPorUserId,
                'fecha_validacion'     => Carbon::now(),
                'observacion'          => null,
            ]);
    }

    /** Rechaza un bloque. Las notas vuelven a PENDIENTE al editarlas. */
    public static function rechazarBloque(int $gestionId, int $grupoId, int $gestionMateriaId, int $numeroExamen, int $validadoPorUserId, string $observacion): int
    {
        $postulacionesEnGrupo = \App\Models\Postulacion::where('gestion_cup_id', $gestionId)
            ->where('grupo_id', $grupoId)
            ->pluck('id');

        return Nota::whereIn('postulacion_id', $postulacionesEnGrupo)
            ->where('gestion_materia_id', $gestionMateriaId)
            ->where('numero_examen', $numeroExamen)
            ->where('estado', EstadoNota::PENDIENTE)
            ->update([
                'estado'               => EstadoNota::RECHAZADA,
                'validado_por_user_id' => $validadoPorUserId,
                'fecha_validacion'     => Carbon::now(),
                'observacion'          => $observacion,
            ]);
    }
}
