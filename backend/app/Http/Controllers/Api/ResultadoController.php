<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Postulacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/*
| ResultadoController
| --------------------------------------------------------------------------
| Consulta publica de resultados CUP (preparada en Ciclo 1).
| El detalle del calculo de notas/cupos llega en ciclos posteriores; por
| ahora devolvemos el estado actual de la postulacion al consultar por su
| codigo_postulante.
|
| GET /api/public/resultados?codigo=0000001
*/
class ResultadoController extends Controller
{
    public function consultar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:12'],
        ]);

        $postulacion = Postulacion::where('codigo_postulante', $data['codigo'])
            ->with(['persona', 'gestion', 'carreraPrimera', 'carreraSegunda', 'turno', 'grupo'])
            ->first();

        if (!$postulacion) {
            return response()->json([
                'encontrado' => false,
                'message' => 'No se encontro ninguna inscripcion con ese codigo.',
            ], 404);
        }

        return response()->json([
            'encontrado' => true,
            'codigo'     => $postulacion->codigo_postulante,
            'persona'    => [
                'nombre_completo' => $postulacion->persona->nombre_completo,
            ],
            'gestion'    => $postulacion->gestion?->codigo,
            'carrera'    => $postulacion->carreraPrimera?->nombre,
            'turno'      => $postulacion->turno?->codigo,
            'grupo'      => $postulacion->grupo?->codigo,
            'estado'     => $postulacion->estado,
            // En Ciclos siguientes aqui se sumara: notas por materia, promedio,
            // ranking, resultado final (ACEPTADO / SIN_CUPO), etc.
            'resultado_final' => null,
        ]);
    }
}
