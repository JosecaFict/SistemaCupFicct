<?php

namespace App\Services;

use App\Enums\EstadoPago;
use App\Enums\EstadoPostulacion;
use App\Models\Pago;
use App\Models\Postulacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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
    public static function iniciar(Postulacion $postulacion, float $monto = 700.00, string $moneda = 'BOB'): Pago
    {
        $modo = config('stripe.mode', 'simulated');

        $pago = Pago::create([
            'postulacion_id'    => $postulacion->id,
            'monto'             => $monto,
            'moneda'            => $moneda,
            'modo'              => $modo,
            'estado'            => EstadoPago::PENDIENTE,
            'referencia'        => 'REF-' . strtoupper(Str::random(10)),
            // Identificadores ficticios; en modo Stripe se sobreescriben abajo.
            'stripe_payment_intent_id' => 'pi_sim_' . Str::random(16),
            'stripe_client_secret'     => 'cs_sim_' . Str::random(24),
            'payload' => ['modo' => $modo, 'simulado' => $modo === 'simulated'],
        ]);

        // En modo Stripe (test/live) creamos una Checkout Session real y
        // guardamos su id (para verificar luego) y la URL (para redirigir).
        if ($modo !== 'simulated') {
            self::crearCheckoutSession($pago, $postulacion, $monto, $moneda);
        }

        BitacoraService::registrar(
            evento: 'PAGO_INICIADO',
            entidad: 'pago',
            entidadId: $pago->id,
            datos: ['postulacion_id' => $postulacion->id, 'modo' => $modo]
        );

        return $pago;
    }

    /**
     * Crea una Checkout Session en Stripe usando el cliente HTTP (sin SDK).
     * La API de Stripe es HTTPS form-encoded con basic-auth (secret como user).
     */
    private static function crearCheckoutSession(Pago $pago, Postulacion $postulacion, float $monto, string $moneda): void
    {
        $secret   = config('stripe.secret');
        $currency = config('stripe.currency', 'usd');
        $frontend = config('stripe.frontend_url');
        $fxBobUsd = (float) config('stripe.fx_bob_usd', 7.20);
        $postId   = $postulacion->id;
        $postulacion->loadMissing('gestion');
        $codigo = $postulacion->gestion?->codigo ?? '';

        // Stripe no soporta BOB: convertimos el monto a USD usando la tasa
        // configurada (STRIPE_FX_BOB_USD). unit_amount va en centavos USD.
        $montoEnMonedaStripe = ($currency === 'usd' && $fxBobUsd > 0)
            ? $monto / $fxBobUsd
            : $monto;
        $unitAmount = (int) round($montoEnMonedaStripe * 100);

        $resp = Http::asForm()
            ->withBasicAuth($secret, '')
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'line_items[0][price_data][currency]'              => $currency,
                'line_items[0][price_data][product_data][name]'    => trim('Inscripcion CUP ' . $codigo) . ' (Bs ' . number_format($monto, 2) . ')',
                'line_items[0][price_data][unit_amount]'           => $unitAmount,
                'line_items[0][quantity]'                          => 1,
                'success_url' => $frontend . '/preinscripcion/' . $postId . '/pago?estado=exito&pago=' . $pago->id,
                'cancel_url'  => $frontend . '/preinscripcion/' . $postId . '/pago?estado=cancelado&pago=' . $pago->id,
                'metadata[pago_id]'        => $pago->id,
                'metadata[postulacion_id]' => $postId,
            ]);

        if ($resp->failed()) {
            // No reventar el flujo: el pago queda PENDIENTE y se registra el error.
            $pago->payload = array_merge($pago->payload ?? [], ['stripe_error' => $resp->json() ?? $resp->body()]);
            $pago->save();
            return;
        }

        $session = $resp->json();
        $pago->stripe_client_secret     = $session['id'] ?? null;              // session id (para verificar)
        $pago->stripe_payment_intent_id = $session['payment_intent'] ?? null; // null hasta que pague
        $pago->payload = array_merge($pago->payload ?? [], ['checkout_url' => $session['url'] ?? null]);
        $pago->save();
    }

    /**
     * Confirma el resultado del pago.
     * En modo 'simulated' se decide segun la referencia del frontend.
     * En modo 'test'/'live' aqui se llamaria a la API de Stripe (futuro).
     */
    public static function confirmar(Pago $pago, ?string $tarjetaSimulada = null): Pago
    {
        // En modo Stripe la verdad la tiene Stripe: verificamos la sesion.
        if (($pago->modo ?? 'simulated') !== 'simulated') {
            return self::confirmarStripe($pago);
        }

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

    /**
     * Verifica el pago real contra Stripe (modo test/live). Consulta la
     * Checkout Session por su id; si payment_status == 'paid' -> APROBADO.
     */
    private static function confirmarStripe(Pago $pago): Pago
    {
        return DB::transaction(function () use ($pago) {
            $secret    = config('stripe.secret');
            $sessionId = $pago->stripe_client_secret;
            $resultado = EstadoPago::PENDIENTE;
            $payStatus = null;

            if ($sessionId) {
                $resp = Http::withBasicAuth($secret, '')
                    ->get('https://api.stripe.com/v1/checkout/sessions/' . $sessionId);

                if ($resp->ok()) {
                    $session   = $resp->json();
                    $payStatus = $session['payment_status'] ?? null; // paid | unpaid | no_payment_required
                    $resultado = $payStatus === 'paid' ? EstadoPago::APROBADO : EstadoPago::PENDIENTE;
                    if (!empty($session['payment_intent'])) {
                        $pago->stripe_payment_intent_id = $session['payment_intent'];
                    }
                }
            }

            $pago->estado = $resultado;
            $pago->fecha_aprobacion = $resultado === EstadoPago::APROBADO ? now() : null;
            $pago->payload = array_merge($pago->payload ?? [], ['payment_status' => $payStatus]);
            $pago->save();

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
                datos: ['estado_final' => $resultado->value, 'via' => 'stripe']
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
