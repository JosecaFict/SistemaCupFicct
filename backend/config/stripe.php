<?php

/*
| Configuracion de la pasarela de pago (Stripe).
| Se lee via config() (no env() directo) para que funcione aunque se cachee
| la config en produccion (php artisan config:cache).
|
| STRIPE_MODE:
|   - simulated -> pasarela ficticia interna (sin Stripe real).
|   - test|live -> Stripe Checkout real con las claves correspondientes.
|
| Stripe NO soporta BOB: la Checkout Session va en STRIPE_CURRENCY (usd por
| defecto) aunque el sistema registre el monto en Bs.
*/
return [
    'mode'         => env('STRIPE_MODE', 'simulated'),
    'key'          => env('STRIPE_KEY'),
    'secret'       => env('STRIPE_SECRET'),
    'currency'     => env('STRIPE_CURRENCY', 'usd'),
    'frontend_url' => rtrim(env('FRONTEND_URL', 'https://sistema-cup-ficct.vercel.app'), '/'),
];
