<?php

namespace App\Services;

use App\Enums\EstadoPago;
use App\Enums\EstadoPostulacion;
use App\Models\Pago;
use App\Models\Postulacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
| PagoService
| --------------------------------------------------------------------------
| Encapsula la pasarela de pago (CU7). En Ciclo 1 STRIPE_MODE=simulated:
| la "pasarela" responde de manera determinista segun la referencia que pase
| el frontend (compatibilidad con Stripe Test Mode despues).
|
| Reglas de simulacion (referencia / numero ficticio):
|   - referencia que termina en '0000' -> RECHAZADO
|   - referencia que termina en '9999' -> CANCELADO
|   - cualquier otra                   -> APROBADO
|
| Por que en Laravel:
|   - El controlador del frontend no debe saber el flujo de aprobacion.
|   - Cuando se cambie a Stripe real (STRIPE_MODE=test|live) basta con
|     reemplazar el cuerpo de iniciar()/confirmar() sin tocar el controller.
*/
class PagoService
{
    /** Crea el intento de pago en estado PENDIENTE. */
    public static function iniciar(Postulacion $postulacion, float $monto = 100.00, string $moneda = 'BOB'): Pago
    {
        $modo = env('STRIPE_MODE', 'simulated');

        $pago = Pago::create([
            'postulacion_id'    => $postulacion->id,
            'monto'             => $monto,
            'moneda'            => $moneda,
            'modo'              => $modo,
            'estado'            => EstadoPago::PENDIENTE,
            'referencia'        => 'REF-' . strtoupper(Str::random(10)),
            // En Ciclo 1 simulado generamos identificadores ficticios.
            'stripe_payment_intent_id' => 'pi_sim_' . Str::random(16),
            'stripe_client_secret'     => 'cs_sim_' . Str::random(24),
            'payload' => ['modo' => $modo, 'simulado' => $modo === 'simulated'],
        ]);

        BitacoraService::registrar(
            evento: 'PAGO_INICIADO',
            entidad: 'pago',
            entidadId: $pago->id,
            datos: ['postulacion_id' => $postulacion->id, 'modo' => $modo]
        );

        return $pago;
    }

    /**
     * Confirma el resultado del pago.
     * En modo 'simulated' se decide segun la referencia del frontend.
     * En modo 'test'/'live' aqui se llamaria a la API de Stripe (futuro).
     */
    public static function confirmar(Pago $pago, ?string $tarjetaSimulada = null): Pago
    {
        return DB::transaction(function () use ($pago, $tarjetaSimulada) {
            $resultado = self::evaluarSimulacion($tarjetaSimulada ?? $pago->referencia);

            $pago->estado = $resultado;
            $pago->fecha_aprobacion = $resultado === EstadoPago::APROBADO ? now() : null;
            $pago->payload = array_merge($pago->payload ?? [], ['decision' => $resultado->value]);
            $pago->save();

            // Si el pago queda aprobado, la postulacion pasa a PAGO_APROBADO
            // (salvo que ya este en INSCRITO o ANULADO).
            if ($resultado === EstadoPago::APROBADO) {
                $postulacion = $pago->postulacion;
                if (!in_array($postulacion->estado, [EstadoPostulacion::INSCRITO, EstadoPostulacion::ANULADO], true)) {
                    $postulacion->estado = EstadoPostulacion::PAGO_APROBADO;
                    $postulacion->save();
                }
            }

            BitacoraService::registrar(
                evento: 'PAGO_' . $resultado->value,
                entidad: 'pago',
                entidadId: $pago->id,
                datos: ['estado_final' => $resultado->value]
            );

            return $pago;
        });
    }

    /** Simulacion determinista para Ciclo 1. */
    private static function evaluarSimulacion(string $referencia): EstadoPago
    {
        return match (true) {
            str_ends_with($referencia, '0000') => EstadoPago::RECHAZADO,
            str_ends_with($referencia, '9999') => EstadoPago::CANCELADO,
            default                            => EstadoPago::APROBADO,
        };
    }
}
