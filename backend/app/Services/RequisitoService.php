<?php

namespace App\Services;

use App\Enums\EstadoRequisito;
use App\Models\Postulacion;
use App\Models\PostulacionRequisito;
use App\Models\Requisito;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/*
| RequisitoService
| --------------------------------------------------------------------------
| Maneja el checklist de requisitos por postulacion (CU8).
|
| asegurarChecklist():
|   Crea (idempotente) una fila por cada requisito vigente con estado
|   PENDIENTE. Se invoca al generar el formulario de preinscripcion para que
|   el encargado ya tenga el checklist visible.
|
| marcar():
|   Actualiza el estado de un requisito y registra quien lo verifico.
|   Si el encargado marca observado/rechazado, la postulacion pasa a OBSERVADO.
|
| todosCompletados():
|   True si todos los requisitos OBLIGATORIOS estan en VALIDADO. Usado por
|   InscripcionService antes de confirmar.
*/
class RequisitoService
{
    public static function asegurarChecklist(Postulacion $postulacion): void
    {
        $requisitos = Requisito::orderBy('orden')->get();

        foreach ($requisitos as $r) {
            PostulacionRequisito::firstOrCreate(
                ['postulacion_id' => $postulacion->id, 'requisito_id' => $r->id],
                ['estado' => EstadoRequisito::PENDIENTE]
            );
        }
    }

    public static function marcar(
        PostulacionRequisito $pr,
        EstadoRequisito $estado,
        ?string $observacion = null
    ): PostulacionRequisito {
        return DB::transaction(function () use ($pr, $estado, $observacion) {
            $pr->estado = $estado;
            $pr->observacion = $observacion;
            $pr->verificado_por_user_id = Auth::id();
            $pr->verificado_at = now();
            $pr->save();

            BitacoraService::registrar(
                evento: 'REQUISITO_' . $estado->value,
                entidad: 'postulacion_requisito',
                entidadId: $pr->id,
                datos: ['postulacion_id' => $pr->postulacion_id]
            );

            // Si quedo OBSERVADO/RECHAZADO, la postulacion entra en OBSERVADO.
            if (in_array($estado, [EstadoRequisito::OBSERVADO, EstadoRequisito::RECHAZADO], true)) {
                $postulacion = $pr->postulacion;
                if (!in_array($postulacion->estado->value, ['INSCRITO', 'ANULADO'], true)) {
                    $postulacion->estado = \App\Enums\EstadoPostulacion::OBSERVADO;
                    $postulacion->save();
                }
            }

            return $pr;
        });
    }

    /** True si TODOS los requisitos obligatorios estan en estado VALIDADO. */
    public static function todosCompletados(Postulacion $postulacion): bool
    {
        $obligatoriosIds = Requisito::where('obligatorio', true)->pluck('id');

        $validadosCount = PostulacionRequisito::where('postulacion_id', $postulacion->id)
            ->whereIn('requisito_id', $obligatoriosIds)
            ->where('estado', EstadoRequisito::VALIDADO)
            ->count();

        return $validadosCount >= $obligatoriosIds->count();
    }
}
