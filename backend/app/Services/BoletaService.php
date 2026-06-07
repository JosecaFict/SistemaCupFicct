<?php

namespace App\Services;

use App\Models\AsignacionDocente;
use App\Models\Inscripcion;
use Illuminate\Support\Carbon;

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

        // Horario del grupo: se lee EN VIVO de asignaciones_docente, asi la
        // boleta siempre refleja el estado actual (cuando se asignan/cambian
        // docentes en Ciclo 2, aparece o se actualiza solo). Si el grupo aun
        // no tiene asignaciones -> arreglo vacio y el front muestra "por asignar".
        $horario = self::horarioDelGrupo($grupo?->id);

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
            // Horario compacto por materia (sin docente, a pedido): cada fila
            // = una materia con sus dias, su bloque horario y su aula.
            'horario' => $horario,
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

    /**
     * Construye el horario del grupo a partir de asignaciones_docente.
     * Una fila por materia: { materia, dias, hora, aula }. Vacio si el grupo
     * no tiene asignaciones todavia.
     */
    private static function horarioDelGrupo(?int $grupoId): array
    {
        if (!$grupoId) {
            return [];
        }

        return AsignacionDocente::where('grupo_id', $grupoId)
            ->with(['gestionMateria.materia', 'ambiente'])
            ->orderBy('dias_semana')
            ->orderBy('hora_inicio')
            ->get()
            ->map(fn (AsignacionDocente $a) => [
                'materia' => $a->gestionMateria?->materia?->codigo ?? '-',
                'dias'    => self::formatDias($a->dias_semana),
                'hora'    => self::formatHora($a->hora_inicio) . '–' . self::formatHora($a->hora_fin),
                'aula'    => $a->ambiente?->nombre ?? '-',
            ])
            ->values()
            ->all();
    }

    /** "LU,MI,VI" -> "Lu,Mi,Vi". */
    private static function formatDias(?string $csv): string
    {
        $map = ['LU' => 'Lu', 'MA' => 'Ma', 'MI' => 'Mi', 'JU' => 'Ju', 'VI' => 'Vi', 'SA' => 'Sa', 'DO' => 'Do'];
        $out = [];
        foreach (array_filter(array_map('trim', explode(',', $csv ?? ''))) as $d) {
            $out[] = $map[strtoupper($d)] ?? ucfirst(strtolower($d));
        }
        return implode(',', $out);
    }

    /** Hora a "H:i" (sin segundos), tolerante a Carbon o string. */
    private static function formatHora($t): string
    {
        if (!$t) {
            return '';
        }
        return $t instanceof Carbon ? $t->format('H:i') : substr((string) $t, 0, 5);
    }
}
