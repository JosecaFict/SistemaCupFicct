<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoPostulacion;
use App\Http\Controllers\Controller;
use App\Models\Pago;
use App\Models\Postulacion;
use App\Services\PagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/*
| PagoController (CU7)
| --------------------------------------------------------------------------
| Endpoints publicos de pago. En Ciclo 1 corremos con STRIPE_MODE=simulated:
| el frontend pinta una pantalla estilo Stripe Test Mode y manda una
| referencia o numero de "tarjeta" simulada. PagoService decide el resultado.
|
| POST /api/public/pagos/iniciar       -> crea Pago PENDIENTE
| POST /api/public/pagos/{id}/confirmar -> simula respuesta de pasarela
*/
class PagoController extends Controller
{
    public function iniciar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'postulacion_id' => ['required', 'exists:postulaciones,id'],
            'monto'          => ['nullable', 'numeric', 'min:1'],
            'moneda'         => ['nullable', 'string', 'size:3'],
        ]);

        $postulacion = Postulacion::findOrFail($data['postulacion_id']);

        // Solo se puede pagar si esta en FORMULARIO_GENERADO (o reintento si previo fue rechazado)
        if (in_array($postulacion->estado, [EstadoPostulacion::INSCRITO, EstadoPostulacion::ANULADO], true)) {
            return response()->json(['message' => 'No se puede pagar esta postulacion en su estado actual.'], 422);
        }
        if ($postulacion->estado === EstadoPostulacion::PREINSCRITO) {
            return response()->json(['message' => 'Genere primero el formulario de preinscripcion.'], 422);
        }

        // El monto es AUTORITATIVO del servidor: lo define la gestion
        // (costo_inscripcion), no el cliente. Default 700 si no estuviera cargado.
        $postulacion->loadMissing('gestion');
        $monto = (float) ($postulacion->gestion?->costo_inscripcion ?? 700.00);

        $pago = PagoService::iniciar($postulacion, $monto, 'BOB');

        return response()->json([
            'pago' => $pago,
            'modo' => $pago->modo,
            // El frontend simula Stripe Elements con este secret (modo simulado)
            'client_secret' => $pago->stripe_client_secret,
            // En modo Stripe (test/live): URL de la Checkout Session para redirigir.
            'checkout_url' => $pago->payload['checkout_url'] ?? null,
            // Sugerencias para la simulacion
            'tarjetas_de_prueba' => [
                'aprueba'  => '4242 4242 4242 4242',
                'rechaza'  => 'XXXX XXXX XXXX 0000',
                'cancela'  => 'XXXX XXXX XXXX 9999',
            ],
        ], 201);
    }

    public function confirmar(Request $request, Pago $pago): JsonResponse
    {
        $data = $request->validate([
            'tarjeta_simulada' => ['nullable', 'string', 'max:50'],
        ]);

        $pagoActualizado = PagoService::confirmar($pago, $data['tarjeta_simulada'] ?? null);

        return response()->json([
            'pago'        => $pagoActualizado,
            'postulacion' => $pagoActualizado->postulacion->fresh(),
        ]);
    }
}
