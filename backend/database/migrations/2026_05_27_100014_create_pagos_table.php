<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Tabla 'pagos'
| --------------------------------------------------------------------------
| Pagos asociados a una postulacion (CU7). Una postulacion puede tener
| VARIOS intentos de pago (ej. rechazo y reintento).
|
| Estados del pago (independientes del estado de la postulacion):
|   PENDIENTE   (creado, esperando respuesta de pasarela)
|   APROBADO    (pago confirmado por pasarela)
|   RECHAZADO   (rechazado por pasarela o por el banco)
|   CANCELADO   (cancelado por el postulante o por timeout)
|
| Modo:
|   simulated  (Ciclo 1, sin Stripe real -- pasarela mock determinista)
|   test       (Stripe Test Mode con claves reales de prueba)
|   live       (produccion futura)
|
| Campos Stripe:
|  - stripe_payment_intent_id: id del PaymentIntent en Stripe
|  - stripe_session_id:        id del Checkout Session (si se usa Checkout)
|  - stripe_client_secret:     secreto para confirmar el pago desde el SPA
*/
return new class extends Migration {
    public function up(): void
    {
        Schema::create('pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('postulacion_id')->constrained('postulaciones')->cascadeOnDelete();
            $table->decimal('monto', 10, 2);
            $table->string('moneda', 3)->default('BOB');
            $table->enum('modo', ['simulated', 'test', 'live'])->default('simulated');
            $table->enum('estado', ['PENDIENTE', 'APROBADO', 'RECHAZADO', 'CANCELADO'])->default('PENDIENTE');
            $table->string('stripe_payment_intent_id', 100)->nullable();
            $table->string('stripe_session_id', 200)->nullable();
            $table->string('stripe_client_secret', 200)->nullable();
            $table->string('referencia', 100)->nullable();           // codigo interno
            $table->json('payload')->nullable();                     // respuesta cruda de la pasarela
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
