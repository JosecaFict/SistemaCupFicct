<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AsignacionDocente;
use App\Models\GestionCup;
use App\Models\GestionMateria;
use App\Models\Grupo;
use App\Models\Postulacion;
use App\Models\Resultado;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/*
| ReporteController (Ciclo 2)
| --------------------------------------------------------------------------
| Genera los 8 reportes obligatorios del documento de la docente:
|   1. listaGeneral        -> lista general de postulantes
|   2. aprobados           -> postulantes aprobados (con ranking)
|   3. reprobados          -> postulantes reprobados (con motivo)
|   4. promedios           -> promedios generales y por carrera
|   5. grupos              -> grupos habilitados y ocupacion
|   6. estadisticasMateria -> promedios por materia y examen
|   7. docentesGrupos      -> docentes con sus grupos asignados
|   8. gruposAprobados     -> ranking de grupos por % aprobacion
|
| Todos los reportes aceptan ?gestion_id y devuelven PDF inline o adjunto.
| Acceso: ADMINISTRADOR + COORDINADOR.
*/
class ReporteController extends Controller
{
    private function gestion(Request $r): GestionCup
    {
        $r->validate(['gestion_id' => ['required', 'exists:gestiones_cup,id']]);
        return GestionCup::findOrFail($r->query('gestion_id'));
    }

    private function descarga(string $vista, array $data, string $nombre): Response
    {
        $pdf = Pdf::loadView($vista, $data)->setPaper('A4', 'portrait');
        return $pdf->stream($nombre . '.pdf');
    }

    // 1. LISTA GENERAL DE POSTULANTES
    public function listaGeneral(Request $request): Response
    {
        $gestion = $this->gestion($request);
        $postulaciones = Postulacion::with([
            'persona:id,nombre,apellido_paterno,apellido_materno,documento',
            'carreraPrimera:id,codigo,nombre',
            'carreraSegunda:id,codigo,nombre',
            'grupo:id,codigo',
        ])
            ->where('gestion_cup_id', $gestion->id)
            ->orderBy('codigo_postulante')
            ->get();

        $filas = $postulaciones->map(fn ($p) => [
            'codigo'           => $p->codigo_postulante,
            'nombre_completo'  => trim(
                ($p->persona?->nombre ?? '') . ' ' .
                ($p->persona?->apellido_paterno ?? '') . ' ' .
                ($p->persona?->apellido_materno ?? '')
            ),
            'documento'        => $p->persona?->documento,
            'carrera_primera'  => $p->carreraPrimera?->codigo,
            'carrera_segunda'  => $p->carreraSegunda?->codigo,
            'grupo'            => $p->grupo?->codigo,
            'estado'           => $p->estado?->value ?? $p->estado,
        ])->toArray();

        return $this->descarga('reportes.lista-general', [
            'titulo'  => 'Lista general de postulantes',
            'gestion' => $gestion,
            'filas'   => $filas,
        ], "lista-general-{$gestion->codigo}");
    }

    // 2. POSTULANTES APROBADOS
    public function aprobados(Request $request): Response
    {
        $gestion = $this->gestion($request);
        $postulacionIds = Postulacion::where('gestion_cup_id', $gestion->id)->pluck('id');

        $resultados = Resultado::with([
            'postulacion.persona:id,nombre,apellido_paterno,apellido_materno',
            'carreraAsignada:id,codigo,nombre',
        ])
            ->whereIn('postulacion_id', $postulacionIds)
            ->where('estado_final', 'ACEPTADO')
            ->orderBy('ranking_global')
            ->get();

        $filas = $resultados->map(function ($r) {
            $p = $r->postulacion;
            $sufijo = $r->opcion_aceptada?->value === 'PRIMERA' ? '1ra'
                   : ($r->opcion_aceptada?->value === 'SEGUNDA' ? '2da' : '');
            return [
                'ranking'         => $r->ranking_global,
                'codigo_publico'  => $p?->codigo_postulante . ($sufijo ? "-{$sufijo}" : ''),
                'nombre_completo' => trim(
                    ($p?->persona?->nombre ?? '') . ' ' .
                    ($p?->persona?->apellido_paterno ?? '') . ' ' .
                    ($p?->persona?->apellido_materno ?? '')
                ),
                'carrera'         => ($r->carreraAsignada?->codigo ?? '-') . '  ' . ($r->carreraAsignada?->nombre ?? ''),
                'opcion'          => $r->opcion_aceptada?->value,
                'nota_final'      => $r->nota_final,
            ];
        })->toArray();

        return $this->descarga('reportes.aprobados', [
            'titulo'  => 'Postulantes aprobados',
            'gestion' => $gestion,
            'filas'   => $filas,
        ], "aprobados-{$gestion->codigo}");
    }

    // 3. POSTULANTES REPROBADOS
    public function reprobados(Request $request): Response
    {
        $gestion = $this->gestion($request);
        $postulacionIds = Postulacion::where('gestion_cup_id', $gestion->id)->pluck('id');

        $resultados = Resultado::with([
            'postulacion.persona:id,nombre,apellido_paterno,apellido_materno,documento',
            'postulacion.carreraPrimera:id,codigo,nombre',
        ])
            ->whereIn('postulacion_id', $postulacionIds)
            ->where('estado_final', 'REPROBADO')
            ->orderByDesc('nota_final')
            ->get();

        $filas = $resultados->map(function ($r) {
            $p = $r->postulacion;
            return [
                'codigo'           => $p?->codigo_postulante,
                'nombre_completo'  => trim(
                    ($p?->persona?->nombre ?? '') . ' ' .
                    ($p?->persona?->apellido_paterno ?? '') . ' ' .
                    ($p?->persona?->apellido_materno ?? '')
                ),
                'documento'        => $p?->persona?->documento,
                'carrera_primera'  => $p?->carreraPrimera?->codigo,
                'nota_final'       => $r->nota_final,
                'motivo'           => $r->motivo,
            ];
        })->toArray();

        return $this->descarga('reportes.reprobados', [
            'titulo'  => 'Postulantes reprobados',
            'gestion' => $gestion,
            'filas'   => $filas,
        ], "reprobados-{$gestion->codigo}");
    }

    // 4. PROMEDIOS GENERALES
    public function promedios(Request $request): Response
    {
        $gestion = $this->gestion($request);
        $postulacionIds = Postulacion::where('gestion_cup_id', $gestion->id)->pluck('id');

        $stats = Resultado::whereIn('postulacion_id', $postulacionIds)
            ->selectRaw('COUNT(*) as total, AVG(nota_final) as prom, MIN(nota_final) as nmin, MAX(nota_final) as nmax')
            ->first();

        $kpis = [
            'total'            => (int) ($stats->total ?? 0),
            'promedio_general' => (float) ($stats->prom ?? 0),
            'nota_minima'      => (float) ($stats->nmin ?? 0),
            'nota_maxima'      => (float) ($stats->nmax ?? 0),
        ];

        // Promedio por carrera (1ra opcion)
        $porCarrera = DB::table('postulaciones as p')
            ->join('resultados as r', 'r.postulacion_id', '=', 'p.id')
            ->join('carreras as c', 'p.carrera_primera_id', '=', 'c.id')
            ->where('p.gestion_cup_id', $gestion->id)
            ->select(
                'c.codigo', 'c.nombre',
                DB::raw('COUNT(*) as cantidad'),
                DB::raw('AVG(r.nota_final) as promedio'),
                DB::raw('MIN(r.nota_final) as nmin'),
                DB::raw('MAX(r.nota_final) as nmax')
            )
            ->groupBy('c.codigo', 'c.nombre')
            ->orderBy('c.codigo')
            ->get()
            ->map(fn ($c) => [
                'codigo'   => $c->codigo,
                'nombre'   => $c->nombre,
                'cantidad' => (int) $c->cantidad,
                'promedio' => (float) $c->promedio,
                'min'      => (float) $c->nmin,
                'max'      => (float) $c->nmax,
            ])->toArray();

        return $this->descarga('reportes.promedios', [
            'titulo'     => 'Promedios generales',
            'gestion'    => $gestion,
            'kpis'       => $kpis,
            'porCarrera' => $porCarrera,
        ], "promedios-{$gestion->codigo}");
    }

    // 5. GRUPOS HABILITADOS
    public function grupos(Request $request): Response
    {
        $gestion = $this->gestion($request);

        $grupos = Grupo::with(['turno:id,codigo,nombre', 'ambiente:id,nombre'])
            ->where('gestion_cup_id', $gestion->id)
            ->orderBy('codigo')
            ->get();

        $totalInscritos = Postulacion::where('gestion_cup_id', $gestion->id)
            ->whereIn('estado', ['INSCRITO', 'ACEPTADO', 'REPROBADO', 'SIN_CUPO'])
            ->count();

        $kpis = [
            'total_grupos'    => $grupos->count(),
            'activos'         => $grupos->where('estado', 'ACTIVO')->count(),
            'total_inscritos' => $totalInscritos,
            'capacidad_total' => $grupos->sum('capacidad'),
        ];

        $filas = $grupos->map(fn ($g) => [
            'codigo'    => $g->codigo,
            'turno'     => $g->turno?->nombre,
            'ambiente'  => $g->ambiente?->nombre,
            'capacidad' => $g->capacidad,
            'inscritos' => $g->inscritos_actuales,
            'ocupacion' => $g->capacidad > 0 ? ($g->inscritos_actuales / $g->capacidad * 100) : 0,
            'estado'    => $g->estado,
        ])->toArray();

        return $this->descarga('reportes.grupos', [
            'titulo'  => 'Grupos habilitados',
            'gestion' => $gestion,
            'kpis'    => $kpis,
            'filas'   => $filas,
        ], "grupos-{$gestion->codigo}");
    }

    // 6. ESTADISTICAS POR MATERIA
    public function estadisticasMateria(Request $request): Response
    {
        $gestion = $this->gestion($request);
        $postulacionIds = Postulacion::where('gestion_cup_id', $gestion->id)->pluck('id');

        $rows = DB::table('notas as n')
            ->join('gestion_materias as gm', 'n.gestion_materia_id', '=', 'gm.id')
            ->join('materias as m', 'gm.materia_id', '=', 'm.id')
            ->whereIn('n.postulacion_id', $postulacionIds)
            ->where('n.estado', 'VALIDADA')
            ->select(
                'm.codigo as materia_codigo', 'm.nombre as materia_nombre', 'n.numero_examen',
                DB::raw('COUNT(*) as cantidad'),
                DB::raw('AVG(n.valor) as promedio'),
                DB::raw('MIN(n.valor) as nmin'),
                DB::raw('MAX(n.valor) as nmax'),
                DB::raw('COUNT(*) FILTER (WHERE n.descalifica) as descalifican')
            )
            ->groupBy('m.codigo', 'm.nombre', 'n.numero_examen')
            ->orderBy('m.codigo')
            ->orderBy('n.numero_examen')
            ->get();

        $filas = $rows->map(fn ($r) => [
            'materia_codigo'   => $r->materia_codigo,
            'materia_nombre'   => $r->materia_nombre,
            'numero_examen'    => (int) $r->numero_examen,
            'cantidad'         => (int) $r->cantidad,
            'promedio'         => (float) $r->promedio,
            'min'              => (float) $r->nmin,
            'max'              => (float) $r->nmax,
            'descalifican'     => (int) $r->descalifican,
            'pct_descalifican' => $r->cantidad > 0 ? ($r->descalifican / $r->cantidad * 100) : 0,
        ])->toArray();

        return $this->descarga('reportes.estadisticas-materia', [
            'titulo'  => 'Estadisticas por materia',
            'gestion' => $gestion,
            'filas'   => $filas,
        ], "estadisticas-materia-{$gestion->codigo}");
    }

    // 7. DOCENTES POR GRUPOS
    public function docentesGrupos(Request $request): Response
    {
        $gestion = $this->gestion($request);
        $asignaciones = AsignacionDocente::with([
            'docente:id,nombre,apellidos,email',
            'grupo:id,codigo',
            'gestionMateria.materia:id,codigo,nombre',
            'ambiente:id,nombre',
        ])
            ->where('gestion_cup_id', $gestion->id)
            ->get();

        $porDocente = $asignaciones->groupBy('docente_user_id');

        $filas = $porDocente->map(function ($grupo) {
            $primera = $grupo->first();
            return [
                'docente_nombre' => trim(($primera->docente?->nombre ?? '') . ' ' . ($primera->docente?->apellidos ?? '')),
                'docente_email'  => $primera->docente?->email,
                'asignaciones'   => $grupo->map(fn ($a) => [
                    'grupo'       => $a->grupo?->codigo,
                    'materia'     => $a->gestionMateria?->materia?->codigo,
                    'dias_semana' => $a->dias_semana,
                    'hora_inicio' => (string) $a->hora_inicio,
                    'hora_fin'    => (string) $a->hora_fin,
                    'ambiente'    => $a->ambiente?->nombre,
                ])->values()->toArray(),
            ];
        })->values()->toArray();

        return $this->descarga('reportes.docentes-grupos', [
            'titulo'  => 'Docentes por grupos',
            'gestion' => $gestion,
            'filas'   => $filas,
        ], "docentes-grupos-{$gestion->codigo}");
    }

    // 8. RANKING DE GRUPOS POR % APROBACION
    public function gruposAprobados(Request $request): Response
    {
        $gestion = $this->gestion($request);

        $rows = DB::table('postulaciones as p')
            ->join('grupos as g', 'p.grupo_id', '=', 'g.id')
            ->join('turnos as t', 'g.turno_id', '=', 't.id')
            ->where('p.gestion_cup_id', $gestion->id)
            ->select(
                'g.id as grupo_id', 'g.codigo as grupo_codigo', 't.codigo as turno_codigo',
                DB::raw('COUNT(*) as total'),
                DB::raw("COUNT(*) FILTER (WHERE p.estado='ACEPTADO')  as aceptados"),
                DB::raw("COUNT(*) FILTER (WHERE p.estado='REPROBADO') as reprobados"),
                DB::raw("COUNT(*) FILTER (WHERE p.estado='SIN_CUPO')  as sin_cupo")
            )
            ->groupBy('g.id', 'g.codigo', 't.codigo')
            ->get()
            ->sortByDesc(function ($r) {
                return $r->total > 0 ? ($r->aceptados / $r->total) : 0;
            })
            ->values();

        $filas = $rows->map(fn ($r) => [
            'grupo_codigo'  => $r->grupo_codigo,
            'turno_codigo'  => $r->turno_codigo,
            'total'         => (int) $r->total,
            'aceptados'     => (int) $r->aceptados,
            'reprobados'    => (int) $r->reprobados,
            'sin_cupo'      => (int) $r->sin_cupo,
            'pct_aprobacion'=> $r->total > 0 ? ($r->aceptados / $r->total * 100) : 0,
        ])->toArray();

        return $this->descarga('reportes.grupos-aprobados', [
            'titulo'  => 'Grupos con mayor cantidad de aprobados',
            'gestion' => $gestion,
            'filas'   => $filas,
        ], "grupos-aprobados-{$gestion->codigo}");
    }
}
