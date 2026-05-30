<?php

namespace App\Services;

use App\Models\Inscripcion;

/*
| BoletaService
| --------------------------------------------------------------------------
| Construye el payload de la boleta (CU10). El frontend usa este JSON para
| pintar BoletaPreview y eventualmente imprimirlo.
|
| En Ciclo 1 NO generamos PDF real -- la boleta se renderiza en el navegador
| y se imprime con window.print(). PDF nativo se hara en Ciclo 6 cuando el
| modulo este estable.
*/
class BoletaService
{
    public static function payload(Inscripcion $inscripcion): array
    {
        $inscripcion->loadMissing([
            'postulacion.persona',
            'postulacion.gestion',
            'postulacion.carreraPrimera',
            'postulacion.carreraSegunda',
            'grupo.ambiente',
            'turno',
            'confirmadaPor',
        ]);

        $postulacion = $inscripcion->postulacion;
        $persona     = $postulacion->persona;
        $grupo       = $inscripcion->grupo;
        $ambiente    = $grupo?->ambiente;

        return [
            'codigo_postulante' => $inscripcion->codigo_postulante,
            'nombre_completo'   => $persona->nombre_completo,
            'documento'         => [
                'tipo'      => $persona->tipo_documento,
                'numero'    => $persona->documento,
                'expedido'  => $persona->expedido,
            ],
            'gestion'           => [
                'codigo' => $postulacion->gestion->codigo,
                'nombre' => $postulacion->gestion->nombre,
            ],
            'turno' => [
                'codigo' => $inscripcion->turno->codigo,
                'nombre' => $inscripcion->turno->nombre,
                'horario' => trim(($inscripcion->turno->hora_inicio ?? '') . ' - ' . ($inscripcion->turno->hora_fin ?? '')),
            ],
            'grupo' => [
                'codigo' => $grupo->codigo,
                'capacidad' => $grupo->capacidad,
            ],
            'carrera_primera' => $postulacion->carreraPrimera?->nombre,
            'carrera_segunda' => $postulacion->carreraSegunda?->nombre,
            'modalidad'       => $ambiente?->modalidad,
            'aula_o_enlace'   => $ambiente?->modalidad === 'VIRTUAL'
                ? $ambiente?->enlace
                : trim(($ambiente?->nombre ?? '') . ' ' . ($ambiente?->ubicacion ?? '')),
            'fecha_inscripcion'   => $inscripcion->fecha_inscripcion?->toIso8601String(),
            'confirmada_por'      => $inscripcion->confirmadaPor?->nombre_completo,
        ];
    }
}
