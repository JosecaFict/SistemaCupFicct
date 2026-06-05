<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GestionCup;
use App\Models\Grupo;
use App\Models\Pago;
use App\Models\Postulacion;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/*
| DashboardController -- Resumen para el panel administrativo.
|
| Cumple el requerimiento del documento de la docente:
|   Indicadores estadisticos:
|     - Total inscritos
|     - Total aprobados
|     - Total reprobados (SIN_CUPO)
|     - Total grupos habilitados
|
| Acepta ?gestion_id para filtrar. Si no se manda:
|   1. Usa la gestion ACTIVA
|   2. Si no hay ACTIVA, usa la mas reciente (para que el panel tenga
|      datos historicos visibles en defensa)
|
| Optimizacion: usa GROUP BY para evitar N queries (1 query agregada por
| postulaciones, pagos, usuarios; resto son lookups simples).
*/
class DashboardController extends Controller
{
    public function resumen(Request $request): JsonResponse
    {
        $gestion = $this->resolverGestion($request);

        $postulacionesQuery = Postulacion::query();
        if ($gestion) {
            $postulacionesQuery->where('gestion_cup_id', $gestion->id);
        }

        // 1 query agrupada por estado de postulaciones
        $countsPostulaciones = (clone $postulacionesQuery)
            ->select('estado', DB::raw('COUNT(*) as cant'))
            ->groupBy('estado')
            ->pluck('cant', 'estado');

        // 1 query agrupada por estado de pagos (filtrada por gestion)
        $countsPagos = Pago::query()
            ->when($gestion, function ($q) use ($gestion) {
                $q->whereHas('postulacion', fn ($pp) => $pp->where('gestion_cup_id', $gestion->id));
            })
            ->select('estado', DB::raw('COUNT(*) as cant'))
            ->groupBy('estado')
            ->pluck('cant', 'estado');

        // 1 query: usuarios totales y activos
        $usuariosStats = User::selectRaw('COUNT(*) as total, COUNT(*) FILTER (WHERE activo) as activos')->first();

        // 1 query: grupos habilitados de la gestion
        $gruposActivos = $gestion
            ? Grupo::where('gestion_cup_id', $gestion->id)->where('estado', 'ACTIVO')->count()
            : Grupo::where('estado', 'ACTIVO')->count();

        // ----- KPIs OBLIGATORIOS (documento de la docente) -----
        // Total inscritos: postulaciones que llegaron a inscribirse
        //   (INSCRITO + las que ya pasaron al estado final ACEPTADO/SIN_CUPO).
        $totalInscritos = (int) (
            ($countsPostulaciones['INSCRITO'] ?? 0)
          + ($countsPostulaciones['ACEPTADO'] ?? 0)
          + ($countsPostulaciones['SIN_CUPO'] ?? 0)
        );
        $totalAprobados  = (int) ($countsPostulaciones['ACEPTADO'] ?? 0);
        $totalReprobados = (int) ($countsPostulaciones['SIN_CUPO'] ?? 0);

        return response()->json([
            'gestion' => $gestion ? [
                'id'                          => $gestion->id,
                'codigo'                      => $gestion->codigo,
                'nombre'                      => $gestion->nombre,
                'estado'                      => $gestion->estado,
                'fecha_inicio_preinscripcion' => $gestion->fecha_inicio_preinscripcion,
                'fecha_cierre_preinscripcion' => $gestion->fecha_cierre_preinscripcion,
                'cantidad_examenes'           => $gestion->cantidad_examenes,
                'capacidad_maxima_grupo'      => $gestion->capacidad_maxima_grupo,
                'nota_minima_aprobacion'      => $gestion->nota_minima_aprobacion,
                'estimado_postulantes'        => $gestion->estimado_postulantes,
                'turnos_habilitados'          => $gestion->turnos_habilitados,
            ] : null,

            // KPIs obligatorios del documento docente
            'kpis' => [
                'total_inscritos'           => $totalInscritos,
                'total_aprobados'           => $totalAprobados,
                'total_reprobados'          => $totalReprobados,
                'total_grupos_habilitados'  => $gruposActivos,
            ],

            'postulaciones' => [
                'total'         => (int) array_sum($countsPostulaciones->all()),
                'preinscritos'  => (int) ($countsPostulaciones['PREINSCRITO'] ?? 0),
                'form_generado' => (int) ($countsPostulaciones['FORMULARIO_GENERADO'] ?? 0),
                'pago_aprobado' => (int) ($countsPostulaciones['PAGO_APROBADO'] ?? 0),
                'observados'    => (int) ($countsPostulaciones['OBSERVADO'] ?? 0),
                'inscritos'     => (int) ($countsPostulaciones['INSCRITO'] ?? 0),
                'anulados'      => (int) ($countsPostulaciones['ANULADO'] ?? 0),
                'aceptados'     => (int) ($countsPostulaciones['ACEPTADO'] ?? 0),
                'sin_cupo'      => (int) ($countsPostulaciones['SIN_CUPO'] ?? 0),
            ],
            'pagos' => [
                'aprobados'  => (int) ($countsPagos['APROBADO']  ?? 0),
                'pendientes' => (int) ($countsPagos['PENDIENTE'] ?? 0),
                'rechazados' => (int) ($countsPagos['RECHAZADO'] ?? 0),
                'cancelados' => (int) ($countsPagos['CANCELADO'] ?? 0),
            ],
            'usuarios' => [
                'total'   => (int) $usuariosStats->total,
                'activos' => (int) $usuariosStats->activos,
            ],
        ]);
    }

    /** Listado de gestiones para el selector del dashboard. */
    public function gestionesParaDashboard(): JsonResponse
    {
        $gestiones = GestionCup::orderByDesc('id')
            ->select('id', 'codigo', 'nombre', 'estado')
            ->get();

        return response()->json($gestiones);
    }

    /** Resuelve que gestion usar segun query param o estado. */
    private function resolverGestion(Request $request): ?GestionCup
    {
        if ($id = $request->query('gestion_id')) {
            return GestionCup::find($id);
        }

        return GestionCup::where('estado', 'ACTIVA')->latest()->first()
            ?? GestionCup::latest()->first();
    }
}
