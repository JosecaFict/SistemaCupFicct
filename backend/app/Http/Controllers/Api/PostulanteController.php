<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoPostulacion;
use App\Http\Controllers\Controller;
use App\Models\Persona;
use App\Models\Postulacion;
use App\Services\BitacoraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/*
| PostulanteController (CU4)
| --------------------------------------------------------------------------
| Gestion interna de postulantes (Encargado/Admin). El postulante NO es un
| usuario con login -- estos endpoints son para el panel administrativo.
*/
class PostulanteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = Postulacion::with(['persona', 'gestion', 'carreraPrimera', 'carreraSegunda', 'turno', 'grupo']);

        if ($busqueda = $request->query('q')) {
            $q->whereHas('persona', function ($qq) use ($busqueda) {
                $qq->where('documento', 'ilike', "%{$busqueda}%")
                   ->orWhere('nombre', 'ilike', "%{$busqueda}%")
                   ->orWhere('apellido_paterno', 'ilike', "%{$busqueda}%")
                   ->orWhere('apellido_materno', 'ilike', "%{$busqueda}%");
            })->orWhere('codigo_postulante', 'ilike', "%{$busqueda}%");
        }

        if ($estado = $request->query('estado')) {
            $q->where('estado', $estado);
        }

        if ($gestionId = $request->query('gestion_cup_id')) {
            $q->where('gestion_cup_id', $gestionId);
        }

        // Filtro por estado de la gestion (ACTIVA / CERRADA / BORRADOR).
        if ($gestionEstado = $request->query('gestion_estado')) {
            $q->whereHas('gestion', fn ($qq) => $qq->where('estado', $gestionEstado));
        }

        return response()->json($q->orderByDesc('id')->paginate(20));
    }

    public function show(Postulacion $postulacion): JsonResponse
    {
        return response()->json(
            $postulacion->load([
                'persona', 'gestion', 'colegio',
                'carreraPrimera', 'carreraSegunda',
                'turno', 'grupo.ambiente',
                'postulacionRequisitos.requisito', 'postulacionRequisitos.verificadoPor',
                'pagos',
                'inscripcion.confirmadaPor',
            ])
        );
    }

    /** Modifica datos personales del postulante. */
    public function updatePersona(Request $request, Persona $persona): JsonResponse
    {
        $data = $request->validate([
            'nombre'           => ['sometimes', 'string', 'max:100'],
            'apellido_paterno' => ['sometimes', 'nullable', 'string', 'max:100'],
            'apellido_materno' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sexo'             => ['sometimes', 'nullable', 'in:M,F,O'],
            'fecha_nacimiento' => ['sometimes', 'nullable', 'date'],
            'email'            => ['sometimes', 'nullable', 'email'],
            'telefono'         => ['sometimes', 'nullable', 'string', 'max:30'],
            'direccion'        => ['sometimes', 'nullable', 'string', 'max:250'],
        ]);

        $persona->update($data);

        return response()->json($persona->fresh());
    }

    /** Anula una postulacion. */
    public function anular(Request $request, Postulacion $postulacion): JsonResponse
    {
        $data = $request->validate([
            'motivo' => ['required', 'string', 'max:300'],
        ]);

        if ($postulacion->estado === EstadoPostulacion::INSCRITO) {
            return response()->json(['message' => 'No se puede anular una postulacion ya INSCRITA. Anular la inscripcion primero.'], 422);
        }

        $postulacion->estado          = EstadoPostulacion::ANULADO;
        $postulacion->fecha_anulacion = now();
        $postulacion->observacion     = $data['motivo'];
        $postulacion->save();

        BitacoraService::registrar('POSTULACION_ANULADA', 'postulacion', $postulacion->id, ['motivo' => $data['motivo']]);

        return response()->json($postulacion);
    }
}
