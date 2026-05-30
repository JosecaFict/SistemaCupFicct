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

/*
| CatalogoController
| --------------------------------------------------------------------------
| Expone catalogos publicos que el frontend necesita (carreras, materias,
| turnos, requisitos, ambientes, gestion activa). Sin autenticacion.
*/
class CatalogoController extends Controller
{
    public function carreras(): JsonResponse
    {
        return response()->json(Carrera::where('activa', true)->orderBy('nombre')->get());
    }

    public function materias(): JsonResponse
    {
        return response()->json(Materia::where('activa', true)->orderBy('id')->get());
    }

    public function turnos(): JsonResponse
    {
        return response()->json(Turno::where('activo', true)->orderBy('id')->get());
    }

    public function requisitos(): JsonResponse
    {
        return response()->json(Requisito::orderBy('orden')->get());
    }

    public function ambientes(): JsonResponse
    {
        return response()->json(Ambiente::where('activo', true)->orderBy('nombre')->get());
    }

    /** Devuelve la gestion CUP en estado ACTIVA (la que esta corriendo). */
    public function gestionActiva(): JsonResponse
    {
        $gestion = GestionCup::where('estado', 'ACTIVA')->latest()->first();
        return response()->json($gestion);
    }
}
