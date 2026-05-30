<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoRequisito;
use App\Http\Controllers\Controller;
use App\Models\Postulacion;
use App\Models\PostulacionRequisito;
use App\Services\RequisitoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/*
| RequisitoVerificacionController (CU8)
| --------------------------------------------------------------------------
| Endpoints para el Encargado: ver checklist de una postulacion y marcar
| cada requisito como VALIDADO / OBSERVADO / RECHAZADO.
*/
class RequisitoVerificacionController extends Controller
{
    public function index(Postulacion $postulacion): JsonResponse
    {
        // Asegura que el checklist exista antes de mostrarlo
        RequisitoService::asegurarChecklist($postulacion);

        return response()->json(
            $postulacion->postulacionRequisitos()
                ->with(['requisito', 'verificadoPor'])
                ->get()
                ->sortBy('requisito.orden')
                ->values()
        );
    }

    public function marcar(Request $request, PostulacionRequisito $pr): JsonResponse
    {
        $data = $request->validate([
            'estado'      => ['required', 'in:VALIDADO,OBSERVADO,RECHAZADO,PENDIENTE'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ]);

        $estado = EstadoRequisito::from($data['estado']);
        $pr = RequisitoService::marcar($pr, $estado, $data['observacion'] ?? null);

        return response()->json($pr->load(['requisito', 'verificadoPor']));
    }

    public function completados(Postulacion $postulacion): JsonResponse
    {
        return response()->json([
            'todos_completados' => RequisitoService::todosCompletados($postulacion),
        ]);
    }
}
