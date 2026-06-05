<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

/*
|--------------------------------------------------------------------------
| Bootstrap de la aplicacion Sistema CUP FICCT
|--------------------------------------------------------------------------
| - Habilita Sanctum stateful para que el SPA (React + Vite) pueda autenticarse
|   con cookies en lugar de pasar tokens en cada request.
| - Registra el alias 'role' para el middleware EnsureUserHasRole, que restringe
|   rutas API segun el rol del usuario autenticado.
*/

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Sanctum: convertir requests del SPA en stateful para usar cookies de sesion
        $middleware->statefulApi();

        // Headers de seguridad para TODAS las respuestas (web + api)
        $middleware->append(SecurityHeaders::class);

        // Alias 'role' para usar como: Route::middleware('role:ADMINISTRADOR')
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
