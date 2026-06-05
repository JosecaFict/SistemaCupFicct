<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ambiente;
use App\Models\Carrera;
use App\Models\GestionCup;
use App\Models\Materia;
use App\Models\Requisito;
use App\Models\Turno;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/*
| CatalogoController
| --------------------------------------------------------------------------
| Expone catalogos publicos que el frontend necesita (carreras, materias,
| turnos, requisitos, ambientes, gestion activa). Sin autenticacion.
|
| Optimizacion: los catalogos estaticos se cachean por 15 minutos para
| evitar queries repetidas en cada navegacion. Cuando un ADMIN modifica
| algun catalogo, debe limpiarse el cache (TODO: hook en controllers de
| admin que muten estos modelos).
*/
class CatalogoController extends Controller
{
    private const TTL = 60 * 15; // 15 minutos

    public function carreras(): JsonResponse
    {
        $data = Cache::remember('cat:carreras', self::TTL, fn () =>
            Carrera::where('activa', true)->orderBy('nombre')->get()
        );
        return response()->json($data);
    }

    public function materias(): JsonResponse
    {
        $data = Cache::remember('cat:materias', self::TTL, fn () =>
            Materia::where('activa', true)->orderBy('id')->get()
        );
        return response()->json($data);
    }

    public function turnos(): JsonResponse
    {
        $data = Cache::remember('cat:turnos', self::TTL, fn () =>
            Turno::where('activo', true)->orderBy('id')->get()
        );
        return response()->json($data);
    }

    public function requisitos(): JsonResponse
    {
        $data = Cache::remember('cat:requisitos', self::TTL, fn () =>
            Requisito::orderBy('orden')->get()
        );
        return response()->json($data);
    }

    public function ambientes(): JsonResponse
    {
        $data = Cache::remember('cat:ambientes', self::TTL, fn () =>
            Ambiente::where('activo', true)->orderBy('nombre')->get()
        );
        return response()->json($data);
    }

    /** Gestion CUP en estado ACTIVA. No se cachea (cambia con frecuencia). */
    public function gestionActiva(): JsonResponse
    {
        $gestion = GestionCup::where('estado', 'ACTIVA')->latest()->first();
        return response()->json($gestion);
    }
}
