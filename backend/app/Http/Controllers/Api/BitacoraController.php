<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bitacora;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/*
| BitacoraController -- Lectura de la bitacora basica.
| Solo lectura; los eventos se insertan desde BitacoraService.
*/
class BitacoraController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Bitacora::with('user:id,nombre,apellidos,email');

        if ($evento = $request->query('evento')) {
            $q->where('evento', $evento);
        }
        if ($entidad = $request->query('entidad')) {
            $q->where('entidad', $entidad);
        }

        return response()->json($q->orderByDesc('id')->paginate(30));
    }
}
