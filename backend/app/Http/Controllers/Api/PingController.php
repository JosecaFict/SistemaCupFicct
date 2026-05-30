<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/*
| PingController -- Endpoint de salud para probar la conexion frontend-backend.
| GET /api/ping  -> { ok: true, app: 'Sistema CUP FICCT', time: ... }
*/
class PingController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'ok'   => true,
            'app'  => config('app.name'),
            'time' => now()->toIso8601String(),
        ]);
    }
}
