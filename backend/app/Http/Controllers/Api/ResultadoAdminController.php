<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoResultado;
use App\Enums\OpcionAceptada;
use App\Http\Controllers\Controller;
use App\Models\Resultado;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/*
| ResultadoAdminController (Ciclo 2)
| --------------------------------------------------------------------------
| Vista de resultados del CUP para ADMINISTRADOR y COORDINADOR/AUTORIDAD.
| Permite filtrar por:
|   - gestion_id
|   - carrera_id
|   - estado (ACEPTADO, SIN_CUPO, PENDIENTE_DESEMPATE, TODOS)
|   - opcion (PRIMERA, SEGUNDA)
|   - q (busqueda por codigo o nombre completo)
|
| Endpoints:
|   GET /api/resultados        -> lista paginada con filtros
|   GET /api/resultados/kpis   -> conteos agregados para tarjetas
*/
class ResultadoAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Resultado::query()
            ->with([
                'postulacion.persona:id,nombre,apellido_paterno,apellido_materno,documento,expedido',
                'postulacion.gestion:id,codigo,nombre',
                'postulacion.carreraPrimera:id,codigo,nombre',
                'postulacion.carreraSegunda:id,codigo,nombre',
                'carreraAsignada:id,codigo,nombre',
            ]);

        $this->aplicarFiltros($q, $request);

        $orden = $request->query('orden', 'ranking');
        if ($orden === 'nota_desc') {
            $q->orderByDesc('nota_final');
        } else {
            // Por defecto: ranking global ASC (NULL al final), luego nota DESC
            $q->orderByRaw('ranking_global IS NULL, ranking_global ASC')
              ->orderByDesc('nota_final');
        }

        // per_page configurable (vista compacta puede pedir mas).
        // Min 5, max 500 para evitar abuso.
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(5, min(500, $perPage));

        return response()->json($q->paginate($perPage));
    }

    public function kpis(Request $request): JsonResponse
    {
        $q = Resultado::query();
        $this->aplicarFiltros($q, $request);

        // 1 query agrupada por estado_final y opcion_aceptada
        $rows = (clone $q)
            ->select('estado_final', 'opcion_aceptada', DB::raw('COUNT(*) as cant'))
            ->groupBy('estado_final', 'opcion_aceptada')
            ->get();

        $totalAceptados = 0;
        $primera = 0;
        $segunda = 0;
        $reprobados = 0;
        $sinCupo = 0;
        $pendienteDesempate = 0;

        foreach ($rows as $r) {
            $cant = (int) $r->cant;
            if ($r->estado_final === EstadoResultado::ACEPTADO->value) {
                $totalAceptados += $cant;
                if ($r->opcion_aceptada === OpcionAceptada::PRIMERA->value)      $primera += $cant;
                elseif ($r->opcion_aceptada === OpcionAceptada::SEGUNDA->value)  $segunda += $cant;
            } elseif ($r->estado_final === EstadoResultado::REPROBADO->value) {
                $reprobados += $cant;
            } elseif ($r->estado_final === EstadoResultado::SIN_CUPO->value) {
                $sinCupo += $cant;
            } elseif ($r->estado_final === EstadoResultado::PENDIENTE_DESEMPATE->value) {
                $pendienteDesempate += $cant;
            }
        }

        // Aceptados por carrera (1 query mas)
        $porCarrera = (clone $q)
            ->where('estado_final', EstadoResultado::ACEPTADO->value)
            ->select('carrera_asignada_id', DB::raw('COUNT(*) as cant'))
            ->groupBy('carrera_asignada_id')
            ->with('carreraAsignada:id,codigo,nombre')
            ->get()
            ->map(fn ($r) => [
                'carrera_id'     => $r->carrera_asignada_id,
                'carrera_codigo' => $r->carreraAsignada?->codigo,
                'carrera_nombre' => $r->carreraAsignada?->nombre,
                'cantidad'       => (int) $r->cant,
            ]);

        return response()->json([
            'totales' => [
                'aceptados'           => $totalAceptados,
                'primera_opcion'      => $primera,
                'segunda_opcion'      => $segunda,
                'reprobados'          => $reprobados,
                'sin_cupo'            => $sinCupo,
                'pendiente_desempate' => $pendienteDesempate,
                'total'               => $totalAceptados + $reprobados + $sinCupo + $pendienteDesempate,
            ],
            'por_carrera' => $porCarrera,
        ]);
    }

    /** Aplica filtros de query string a una query de Resultado. */
    private function aplicarFiltros($q, Request $request): void
    {
        if ($gestionId = $request->query('gestion_id')) {
            $q->whereHas('postulacion', fn ($pp) => $pp->where('gestion_cup_id', $gestionId));
        }

        if ($carreraId = $request->query('carrera_id')) {
            $q->where('carrera_asignada_id', $carreraId);
        }

        $estado = $request->query('estado');
        if ($estado && $estado !== 'TODOS') {
            $q->where('estado_final', $estado);
        }

        if ($opcion = $request->query('opcion')) {
            $q->where('opcion_aceptada', $opcion);
        }

        if ($soloPublicados = $request->query('solo_publicados')) {
            $q->where('publicado', true);
        }

        if ($busqueda = $request->query('q')) {
            $q->where(function ($qq) use ($busqueda) {
                $qq->whereHas('postulacion', function ($pp) use ($busqueda) {
                    $pp->where('codigo_postulante', 'ilike', "%{$busqueda}%")
                       ->orWhereHas('persona', function ($ppp) use ($busqueda) {
                           $ppp->where('nombre', 'ilike', "%{$busqueda}%")
                               ->orWhere('apellido_paterno', 'ilike', "%{$busqueda}%")
                               ->orWhere('apellido_materno', 'ilike', "%{$busqueda}%")
                               ->orWhere('documento', 'ilike', "%{$busqueda}%");
                       });
                });
            });
        }
    }
}
