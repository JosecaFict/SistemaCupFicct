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
            ->with([
                'persona',
                'gestion',
                'carreraPrimera',
                'carreraSegunda',
                'turno',
                'grupo',
                'resultado.carreraAsignada',
            ])
            ->first();

        if (!$postulacion) {
            return response()->json([
                'encontrado' => false,
                'message' => 'No se encontro ninguna inscripcion con ese codigo.',
            ], 404);
        }

        // Resultado del calculo (puede ser null si la gestion aun no calculo).
        // Solo se expone al postulante si esta publicado.
        $resultado = $postulacion->resultado;
        $publicado = $resultado?->publicado === true;
        $estadoFinal = $publicado ? $resultado?->estado_final?->value : null;
        $opcionAceptada = $publicado ? $resultado?->opcion_aceptada?->value : null;
        $carreraAsignada = $publicado ? $resultado?->carreraAsignada?->nombre : null;

        return response()->json([
            'encontrado' => true,
            'codigo'     => $postulacion->codigo_postulante,
            'persona'    => [
                'nombre_completo' => $postulacion->persona->nombre_completo,
            ],
            'gestion'    => $postulacion->gestion?->codigo,
            'carrera'    => $postulacion->carreraPrimera?->nombre,
            // Campos del resultado publicado (null si aun no se calculo / publico).
            'carrera_asignada' => $carreraAsignada,
            'opcion_aceptada'  => $opcionAceptada, // PRIMERA, SEGUNDA, NINGUNA, null
            'estado_final'     => $estadoFinal,    // ACEPTADO, SIN_CUPO, PENDIENTE_DESEMPATE, null
            'publicado'        => $publicado,
            'turno'      => $postulacion->turno?->codigo,
            'grupo'      => $postulacion->grupo?->codigo,
            'estado'     => $postulacion->estado,
            'resultado_final' => $estadoFinal, // alias retrocompat
        ]);
    }
}
