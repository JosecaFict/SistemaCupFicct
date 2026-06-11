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

        // Modalidad y aula efectivas: si el grupo tiene su propio ambiente,
        // se usa. Si no, se derivan de los ambientes de las asignaciones
        // docente del grupo (Ciclo 2 ya las trae). Asi la boleta no muestra
        // "Por asignar" cuando la info real esta en las asignaciones.
        [$modalidadEfectiva, $aulaEfectiva] = self::resolverModalidadYAula($ambiente, $grupo?->id);

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
            'modalidad'       => $modalidadEfectiva,
            'aula_o_enlace'   => $aulaEfectiva,
            'fecha_inscripcion'   => $inscripcion->fecha_inscripcion?->toIso8601String(),
            'confirmada_por'      => $inscripcion->confirmadaPor?->nombre_completo,
        ];
    }

    /**
     * Devuelve [$modalidad, $aulaOEnlace] para la boleta.
     * Prioridad:
     *   1. Si el grupo tiene su propio ambiente -> usarlo.
     *   2. Si no, derivar de las asignaciones docente del grupo:
     *      - Si todas usan el mismo ambiente -> mostrar ese.
     *      - Si usan ambientes distintos -> "Por materia (ver horario)".
     *      - Si no hay asignaciones -> "Por asignar".
     */
    private static function resolverModalidadYAula($ambienteGrupo, ?int $grupoId): array
    {
        // Caso 1: el grupo ya tiene su ambiente principal.
        if ($ambienteGrupo) {
            $aula = $ambienteGrupo->modalidad === 'VIRTUAL'
                ? ($ambienteGrupo->enlace ?: 'Por asignar')
                : trim(($ambienteGrupo->nombre ?? '') . ' ' . ($ambienteGrupo->ubicacion ?? ''));
            return [
                $ambienteGrupo->modalidad ?: 'Por asignar',
                $aula !== '' ? $aula : 'Por asignar',
            ];
        }

        if (!$grupoId) {
            return ['Por asignar', 'Por asignar'];
        }

        // Caso 2: derivar de las asignaciones docente del grupo.
        $ambientes = AsignacionDocente::where('grupo_id', $grupoId)
            ->with('ambiente:id,nombre,ubicacion,modalidad,enlace')
            ->get()
            ->pluck('ambiente')
            ->filter()
            ->unique('id')
            ->values();

        if ($ambientes->isEmpty()) {
            return ['Por asignar', 'Por asignar'];
        }

        // Si todas las asignaciones usan el MISMO ambiente -> usarlo como
        // ambiente efectivo del grupo en la boleta.
        if ($ambientes->count() === 1) {
            $a = $ambientes->first();
            $aula = $a->modalidad === 'VIRTUAL'
                ? ($a->enlace ?: 'Por asignar')
                : trim(($a->nombre ?? '') . ' ' . ($a->ubicacion ?? ''));
            return [
                $a->modalidad ?: 'PRESENCIAL',
                $aula !== '' ? $aula : 'Por asignar',
            ];
        }

        // Varios ambientes distintos entre materias: la boleta ya lista cada
        // aula en el horario, asi que aqui solo indicamos "ver horario".
        $modalidades = $ambientes->pluck('modalidad')->unique();
        $modalidadEfectiva = $modalidades->count() === 1
            ? $modalidades->first()
            : 'MIXTA';

        return [$modalidadEfectiva, 'Por materia (ver horario)'];
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

        $asigs = AsignacionDocente::where('grupo_id', $grupoId)
            ->with(['gestionMateria.materia', 'ambiente'])
            ->orderBy('dias_semana')
            ->orderBy('hora_inicio')
            ->get();

        // Ancho del codigo de materia para alinear los "|" (ING -> "ING ").
        $ancho = (int) $asigs->max(fn (AsignacionDocente $a) => mb_strlen($a->gestionMateria?->materia?->codigo ?? '-'));

        return $asigs
            ->map(fn (AsignacionDocente $a) => [
                'materia' => str_pad($a->gestionMateria?->materia?->codigo ?? '-', $ancho),
                'dias'    => self::formatDias($a->dias_semana),
                'hora'    => self::formatHora($a->hora_inicio) . '–' . self::formatHora($a->hora_fin),
                // Aula corta y sin espacios para el formato compacto: "Aula-12".
                'aula'    => str_replace(' ', '-', $a->ambiente?->nombre ?? '-'),
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

    /** Hora a "08:00am" (12h, sin segundos), tolerante a Carbon o string. */
    private static function formatHora($t): string
    {
        if (!$t) {
            return '';
        }
        $c = $t instanceof Carbon ? $t : Carbon::parse((string) $t);
        return $c->format('h:ia');
    }
}
