<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/*
| Middleware: EnsureUserHasRole
| --------------------------------------------------------------------------
| Restringe rutas API segun el codigo de rol del usuario autenticado.
| Uso: Route::middleware('role:ADMINISTRADOR')->group(...)
|      Route::middleware('role:ADMINISTRADOR,ENCARGADO')->group(...)
*/
class EnsureUserHasRole
{
    /**
     * @param  Request  $request
     * @param  Closure  $next
     * @param  string   ...$rolesPermitidos Codigos separados por coma o como argumentos.
     */
    public function handle(Request $request, Closure $next, string ...$rolesPermitidos): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        if (!$user->activo) {
            return response()->json(['message' => 'Usuario inactivo.'], 403);
        }

        // 'role:ADMIN,ENCARGADO' llega como un solo argumento "ADMIN,ENCARGADO"
        $codigos = [];
        foreach ($rolesPermitidos as $r) {
            $codigos = array_merge($codigos, explode(',', $r));
        }
        $codigos = array_map('trim', $codigos);

        if (!in_array($user->rol?->codigo, $codigos, true)) {
            return response()->json(['message' => 'No autorizado para este recurso.'], 403);
        }

        return $next($request);
    }
}
