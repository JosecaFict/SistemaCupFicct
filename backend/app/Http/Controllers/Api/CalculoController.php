<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GestionCup;
use App\Services\BitacoraService;
use App\Services\CalculoResultadosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/*
| CalculoController (Ciclo 2 - CU16, CU17)
| --------------------------------------------------------------------------
| Endpoints para el calculo y publicacion de resultados.
|   GET  /api/calculo/estado?gestion_id=X
|        -> diagnostico: puede calcularse? por que no?
|   POST /api/calculo/calcular
|        -> ejecuta el algoritmo completo
|   POST /api/calculo/publicar
|        -> publica los resultados (los hace visibles al postulante publico)
|   POST /api/calculo/despublicar
|        -> revierte la publicacion (admin)
|
| Acceso: ADMINISTRADOR + COORDINADOR para calcular/publicar.
| Solo ADMIN puede despublicar (autoridad maxima).
*/
class CalculoController extends Controller
{
    public function estado(Request $request): JsonResponse
    {
        $request->validate(['gestion_id' => ['required', 'exists:gestiones_cup,id']]);
        $gestion = GestionCup::findOrFail($request->query('gestion_id'));
        return response()->json(CalculoResultadosService::estadoGestion($gestion));
    }

    public function calcular(Request $request): JsonResponse
    {
        $request->validate(['gestion_id' => ['required', 'exists:gestiones_cup,id']]);
        $gestion = GestionCup::findOrFail($request->input('gestion_id'));

        $estado = CalculoResultadosService::estadoGestion($gestion);
        if (!$estado['puede_calcularse']) {
            return response()->json([
                'mensaje' => 'La gestion no puede ser calculada todavia.',
                'razon'   => $estado['razon_bloqueo'],
                'estado'  => $estado,
            ], 422);
        }

        BitacoraService::registrar('CALCULO_RESULTADOS_INICIADO', 'gestion_cup', $gestion->id);
        $resultado = CalculoResultadosService::calcular($gestion, Auth::id());

        return response()->json([
            'mensaje'  => 'Calculo finalizado correctamente.',
            'resumen'  => $resultado,
            'estado'   => CalculoResultadosService::estadoGestion($gestion->fresh()),
        ]);
    }

    public function publicar(Request $request): JsonResponse
    {
        $request->validate(['gestion_id' => ['required', 'exists:gestiones_cup,id']]);
        $gestion = GestionCup::findOrFail($request->input('gestion_id'));

        $cant = CalculoResultadosService::publicar($gestion, Auth::id());

        return response()->json([
            'mensaje'         => "Se publicaron {$cant} resultados.",
            'cantidad'        => $cant,
            'estado'          => CalculoResultadosService::estadoGestion($gestion->fresh()),
        ]);
    }

    public function despublicar(Request $request): JsonResponse
    {
        $request->validate(['gestion_id' => ['required', 'exists:gestiones_cup,id']]);
        $gestion = GestionCup::findOrFail($request->input('gestion_id'));

        $cant = CalculoResultadosService::despublicar($gestion);
        BitacoraService::registrar('RESULTADOS_DESPUBLICADOS', 'gestion_cup', $gestion->id, ['cantidad' => $cant]);

        return response()->json([
            'mensaje'  => "Se despublicaron {$cant} resultados.",
            'cantidad' => $cant,
        ]);
    }
}
