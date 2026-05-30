<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Grupo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/*
| GrupoController
| --------------------------------------------------------------------------
| Listado y consulta de grupos. La generacion automatica esta en
| GestionCupController::generarGrupos() (CU11). Aqui solo se listan, se
| ajustan capacidades y se asigna ambiente.
*/
class GrupoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Grupo::with(['turno', 'ambiente', 'gestion']);

        if ($gestion = $request->query('gestion_cup_id')) {
            $q->where('gestion_cup_id', $gestion);
        }
        if ($turno = $request->query('turno_id')) {
            $q->where('turno_id', $turno);
        }

        return response()->json($q->orderBy('codigo')->get());
    }

    public function update(Request $request, Grupo $grupo): JsonResponse
    {
        $data = $request->validate([
            'ambiente_id' => ['nullable', 'exists:ambientes,id'],
            'capacidad'   => ['sometimes', 'integer', 'min:1', 'max:500'],
            'estado'      => ['sometimes', 'in:ACTIVO,INACTIVO'],
        ]);

        $grupo->update($data);

        return response()->json($grupo->fresh()->load(['turno', 'ambiente']));
    }
}
