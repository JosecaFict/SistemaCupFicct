<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Postulacion;
use App\Services\BoletaService;
use App\Services\InscripcionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/*
| InscripcionController (CU9, CU10)
| --------------------------------------------------------------------------
| Endpoints del encargado para confirmar la inscripcion y obtener la boleta.
|
| POST /api/encargado/postulaciones/{id}/confirmar  (CU9)
|   Body: { turno_id: 1 }
|   Llama a InscripcionService::confirmar() que aplica todas las validaciones
|   y asigna grupo automaticamente.
|
| GET  /api/encargado/postulaciones/{id}/boleta     (CU10)
|   Devuelve el payload renderizable de la boleta.
*/
class InscripcionController extends Controller
{
    public function confirmar(Request $request, Postulacion $postulacion): JsonResponse
    {
        $data = $request->validate([
            'turno_id' => ['required', 'exists:turnos,id'],
        ]);

        try {
            $inscripcion = InscripcionService::confirmar($postulacion, (int) $data['turno_id']);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message'     => 'Inscripcion confirmada.',
            'inscripcion' => $inscripcion,
            'boleta'      => BoletaService::payload($inscripcion),
        ]);
    }

    public function boleta(Postulacion $postulacion): JsonResponse
    {
        if (!$postulacion->inscripcion) {
            return response()->json(['message' => 'La postulacion aun no esta inscrita.'], 404);
        }

        return response()->json(BoletaService::payload($postulacion->inscripcion));
    }
}
