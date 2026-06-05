<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoNota;
use App\Http\Controllers\Controller;
use App\Models\AsignacionDocente;
use App\Models\Nota;
use App\Models\Postulacion;
use App\Services\BitacoraService;
use App\Services\NotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/*
| NotaValidacionController (Ciclo 2 - CU15)
| --------------------------------------------------------------------------
| Endpoints para COORDINADOR y ADMINISTRADOR para validar notas en BLOQUE.
|   GET  /api/notas/bloques-pendientes?gestion_id=X
|        -> lista de bloques con notas en PENDIENTE
|   POST /api/notas/validar-bloque
|        -> aprueba todo el bloque (notas pasan a VALIDADA)
|   POST /api/notas/rechazar-bloque
|        -> rechaza con observacion obligatoria; el docente debe recargar
|   GET  /api/notas/bloque-detalle?...
|        -> ver el detalle de las notas de un bloque antes de validar
|   PATCH /api/notas/{nota}/override  (solo ADMIN)
|        -> permite modificar una nota VALIDADA por fuerza mayor
*/
class NotaValidacionController extends Controller
{
    public function bloquesPendientes(Request $request): JsonResponse
    {
        $request->validate(['gestion_id' => ['required', 'exists:gestiones_cup,id']]);
        $gestionId = (int) $request->query('gestion_id');

        // Postulaciones de la gestion
        $postulacionIds = Postulacion::where('gestion_cup_id', $gestionId)->pluck('id');

        // Bloques agregados: (grupo_id via postulacion, gestion_materia_id, numero_examen)
        // como Eloquent no tiene buen group by por joins, hacemos query con raw.
        $bloques = DB::table('notas as n')
            ->join('postulaciones as p', 'n.postulacion_id', '=', 'p.id')
            ->join('grupos as g', 'p.grupo_id', '=', 'g.id')
            ->join('gestion_materias as gm', 'n.gestion_materia_id', '=', 'gm.id')
            ->join('materias as m', 'gm.materia_id', '=', 'm.id')
            ->join('turnos as t', 'g.turno_id', '=', 't.id')
            ->where('p.gestion_cup_id', $gestionId)
            ->select(
                'p.grupo_id',
                'g.codigo as grupo_codigo',
                't.codigo as turno_codigo',
                'gm.id as gestion_materia_id',
                'm.codigo as materia_codigo',
                'm.nombre as materia_nombre',
                'n.numero_examen',
                DB::raw('COUNT(*) as total_notas'),
                DB::raw("COUNT(*) FILTER (WHERE n.estado='PENDIENTE')  as pendientes"),
                DB::raw("COUNT(*) FILTER (WHERE n.estado='VALIDADA')   as validadas"),
                DB::raw("COUNT(*) FILTER (WHERE n.estado='RECHAZADA')  as rechazadas"),
                DB::raw('MAX(n.created_at) as ultima_carga'),
                DB::raw("MIN(n.docente_user_id) as docente_user_id")
            )
            ->groupBy('p.grupo_id', 'g.codigo', 't.codigo', 'gm.id', 'm.codigo', 'm.nombre', 'n.numero_examen')
            ->havingRaw("COUNT(*) FILTER (WHERE n.estado='PENDIENTE') > 0")
            ->orderBy('g.codigo')
            ->orderBy('m.codigo')
            ->orderBy('n.numero_examen')
            ->get();

        // Mapear docente para mostrar nombre
        $docenteIds = $bloques->pluck('docente_user_id')->unique()->all();
        $docentes = \App\Models\User::whereIn('id', $docenteIds)
            ->select('id', 'nombre', 'apellidos')
            ->get()
            ->keyBy('id');

        $resultado = $bloques->map(function ($b) use ($docentes) {
            $doc = $docentes->get($b->docente_user_id);
            return [
                'grupo_id'           => (int) $b->grupo_id,
                'grupo_codigo'       => $b->grupo_codigo,
                'turno_codigo'       => $b->turno_codigo,
                'gestion_materia_id' => (int) $b->gestion_materia_id,
                'materia_codigo'     => $b->materia_codigo,
                'materia_nombre'     => $b->materia_nombre,
                'numero_examen'      => (int) $b->numero_examen,
                'total_notas'        => (int) $b->total_notas,
                'pendientes'         => (int) $b->pendientes,
                'validadas'          => (int) $b->validadas,
                'rechazadas'         => (int) $b->rechazadas,
                'docente'            => $doc ? "{$doc->nombre} {$doc->apellidos}" : null,
                'ultima_carga'       => $b->ultima_carga,
            ];
        });

        return response()->json($resultado);
    }

    public function bloqueDetalle(Request $request): JsonResponse
    {
        $data = $request->validate([
            'gestion_id'         => ['required', 'exists:gestiones_cup,id'],
            'grupo_id'           => ['required', 'exists:grupos,id'],
            'gestion_materia_id' => ['required', 'exists:gestion_materias,id'],
            'numero_examen'      => ['required', 'integer', 'min:1', 'max:3'],
        ]);

        $postulacionIds = Postulacion::where('gestion_cup_id', $data['gestion_id'])
            ->where('grupo_id', $data['grupo_id'])
            ->orderBy('codigo_postulante')
            ->pluck('id');

        $notas = Nota::with([
            'postulacion.persona:id,nombre,apellido_paterno,apellido_materno',
            'docente:id,nombre,apellidos',
        ])
            ->whereIn('postulacion_id', $postulacionIds)
            ->where('gestion_materia_id', $data['gestion_materia_id'])
            ->where('numero_examen', $data['numero_examen'])
            ->orderBy(
                Postulacion::select('codigo_postulante')->whereColumn('postulaciones.id', 'notas.postulacion_id')
            )
            ->get();

        return response()->json($notas);
    }

    public function validarBloque(Request $request): JsonResponse
    {
        $data = $request->validate([
            'gestion_id'         => ['required', 'exists:gestiones_cup,id'],
            'grupo_id'           => ['required', 'exists:grupos,id'],
            'gestion_materia_id' => ['required', 'exists:gestion_materias,id'],
            'numero_examen'      => ['required', 'integer', 'min:1', 'max:3'],
        ]);

        $userId = Auth::id();
        $cant = NotaService::validarBloque(
            $data['gestion_id'], $data['grupo_id'], $data['gestion_materia_id'],
            $data['numero_examen'], $userId
        );

        BitacoraService::registrar('NOTAS_VALIDADAS', 'bloque_notas', null, $data + ['cantidad' => $cant]);

        return response()->json([
            'validadas' => $cant,
            'mensaje'   => "Se validaron {$cant} notas en bloque.",
        ]);
    }

    public function rechazarBloque(Request $request): JsonResponse
    {
        $data = $request->validate([
            'gestion_id'         => ['required', 'exists:gestiones_cup,id'],
            'grupo_id'           => ['required', 'exists:grupos,id'],
            'gestion_materia_id' => ['required', 'exists:gestion_materias,id'],
            'numero_examen'      => ['required', 'integer', 'min:1', 'max:3'],
            'observacion'        => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $userId = Auth::id();
        $cant = NotaService::rechazarBloque(
            $data['gestion_id'], $data['grupo_id'], $data['gestion_materia_id'],
            $data['numero_examen'], $userId, $data['observacion']
        );

        BitacoraService::registrar('NOTAS_RECHAZADAS', 'bloque_notas', null, $data + ['cantidad' => $cant]);

        return response()->json([
            'rechazadas' => $cant,
            'mensaje'    => "Se rechazaron {$cant} notas. El docente debe corregirlas.",
        ]);
    }

    /**
     * Override de ADMIN para modificar una nota VALIDADA por fuerza mayor.
     */
    public function override(Request $request, Nota $nota): JsonResponse
    {
        $data = $request->validate([
            'valor'       => ['required', 'numeric', 'min:0', 'max:100'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ]);

        $gestionMateria = $nota->gestionMateria;
        $gestion = $gestionMateria->gestion;

        $valor = round((float) $data['valor'], 2);
        $nota->update([
            'valor'        => $valor,
            'descalifica'  => NotaService::calcularDescalifica($gestion, $valor),
            'observacion'  => $data['observacion'] ?? 'Override por ADMIN',
        ]);

        BitacoraService::registrar('NOTA_MODIFICADA_POR_ADMIN', 'nota', $nota->id, [
            'valor_nuevo' => $valor,
            'observacion' => $data['observacion'] ?? null,
        ]);

        return response()->json(['nota' => $nota->fresh(), 'mensaje' => 'Nota actualizada por ADMIN.']);
    }
}
