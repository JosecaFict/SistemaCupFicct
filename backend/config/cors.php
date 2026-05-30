<?php

/*
|--------------------------------------------------------------------------
| Configuracion de CORS para el frontend Vite
|--------------------------------------------------------------------------
| Permitimos el origen del frontend (Vite dev server) y exponemos las rutas
| api/* y sanctum/csrf-cookie para que el login funcione con cookies de Sanctum.
*/

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:5173'),
        'http://127.0.0.1:5173',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Necesario para que Sanctum envie cookies con el SPA
    'supports_credentials' => true,
];
