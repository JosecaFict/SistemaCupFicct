<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/*
| SecurityHeaders Middleware (Ciclo 2 - Hardening)
| --------------------------------------------------------------------------
| Agrega headers de seguridad estandar a todas las respuestas HTTP:
|   - X-Content-Type-Options: nosniff       (impide MIME sniffing)
|   - X-Frame-Options: SAMEORIGIN           (impide clickjacking)
|   - X-XSS-Protection: 1; mode=block       (defensa adicional contra XSS)
|   - Referrer-Policy: strict-origin-when-cross-origin
|   - Permissions-Policy: limita features del navegador
|   - Strict-Transport-Security              (solo en HTTPS / produccion)
|
| Estos headers complementan las protecciones de Sanctum y CORS sin
| romper el flujo normal del SPA.
*/
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Headers basicos (siempre)
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), interest-cohort=()'
        );

        // HSTS solo en produccion sobre HTTPS (Railway sirve via HTTPS)
        if (app()->environment('production') && $request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }
}
