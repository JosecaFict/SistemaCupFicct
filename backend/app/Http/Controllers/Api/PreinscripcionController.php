<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoPostulacion;
use App\Http\Controllers\Controller;
use App\Models\Colegio;
use App\Models\GestionCup;
use App\Models\Persona;
use App\Models\Postulacion;
use App\Services\BitacoraService;
use App\Services\RequisitoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/*
| PreinscripcionController (CU5, CU6)
| --------------------------------------------------------------------------
| Endpoints PUBLICOS (sin login) para que el postulante se preinscriba.
|
| POST /api/public/preinscripciones        -> crea persona + postulacion (CU5)
| POST /api/public/preinscripciones/{id}/generar-formulario -> CU6
| GET  /api/public/preinscripciones/{id}   -> consulta su estado (con token simple opcional)
|
| Validacion de duplicado (CU4):
|   - CI_BO: (CI_BO + documento + expedido) debe ser unico.
|   - EXT:   (EXT + documento) debe ser unico.
|   El cliente no puede crear DOS postulaciones para la misma gestion con la
|   misma persona (indice unico (persona_id, gestion_cup_id)).
*/
class PreinscripcionController extends Controller
{
    /** CU5 - Crear preinscripcion publica. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            // Datos del documento
            'tipo_documento'   => ['required', Rule::in(['CI_BO', 'EXT'])],
            'documento'        => ['required', 'string', 'max:30'],
            'expedido'         => ['required_if:tipo_documento,CI_BO', 'nullable', 'string', 'max:2'],

            // Datos personales
            'nombre'           => ['required', 'string', 'max:100'],
            'apellido_paterno' => ['nullable', 'string', 'max:100'],
            'apellido_materno' => ['nullable', 'string', 'max:100'],
            'sexo'             => ['nullable', 'in:M,F,O'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'email'            => ['nullable', 'email'],
            'telefono'         => ['nullable', 'string', 'max:30'],
            'direccion'        => ['nullable', 'string', 'max:250'],

            // Colegio
            'colegio_nombre'      => ['nullable', 'string', 'max:200'],
            'colegio_ciudad'      => ['nullable', 'string', 'max:100'],
            'anio_egreso_colegio' => ['nullable', 'integer', 'min:1980', 'max:2100'],

            // Carrera y gestion
            'gestion_cup_id'      => ['required', 'exists:gestiones_cup,id'],
            'carrera_primera_id'  => ['required', 'exists:carreras,id'],
            'carrera_segunda_id'  => ['nullable', 'different:carrera_primera_id', 'exists:carreras,id'],
        ]);

        // La gestion debe tener la preinscripcion abierta: estado ACTIVA, con
        // codigo y fechas cargadas, y hoy dentro del rango (mismo criterio que
        // usa la pantalla). Bloquea gestiones fantasma o fuera de periodo.
        $gestion = GestionCup::findOrFail($data['gestion_cup_id']);
        if (!GestionCup::abiertaPreinscripcion()->whereKey($gestion->id)->exists()) {
            return response()->json([
                'message' => 'La gestion no esta abierta para preinscripciones (estado o fechas).',
            ], 422);
        }

        return DB::transaction(function () use ($data) {
            // Persona: buscar duplicado segun tipo
            $busqueda = [
                'tipo_documento' => $data['tipo_documento'],
                'documento'      => $data['documento'],
            ];
            if ($data['tipo_documento'] === 'CI_BO') {
                $busqueda['expedido'] = $data['expedido'];
            }

            $persona = Persona::firstOrCreate(
                $busqueda,
                [
                    'nombre'           => $data['nombre'],
                    'apellido_paterno' => $data['apellido_paterno'] ?? null,
                    'apellido_materno' => $data['apellido_materno'] ?? null,
                    'sexo'             => $data['sexo'] ?? null,
                    'fecha_nacimiento' => $data['fecha_nacimiento'] ?? null,
                    'email'            => $data['email'] ?? null,
                    'telefono'         => $data['telefono'] ?? null,
                    'direccion'        => $data['direccion'] ?? null,
                ]
            );

            // Colegio firstOrCreate por nombre
            $colegio = null;
            if (!empty($data['colegio_nombre'])) {
                $colegio = Colegio::firstOrCreate(
                    ['nombre' => $data['colegio_nombre']],
                    ['ciudad' => $data['colegio_ciudad'] ?? null]
                );
            }

            // Postulacion (unique per persona-gestion)
            $yaExiste = Postulacion::where('persona_id', $persona->id)
                ->where('gestion_cup_id', $data['gestion_cup_id'])
                ->first();
            if ($yaExiste) {
                return response()->json([
                    'message' => 'Esta persona ya tiene una postulacion en esta gestion.',
                    'postulacion' => $yaExiste,
                ], 422);
            }

            $postulacion = Postulacion::create([
                'persona_id'           => $persona->id,
                'gestion_cup_id'       => $data['gestion_cup_id'],
                'colegio_id'           => $colegio?->id,
                'anio_egreso_colegio'  => $data['anio_egreso_colegio'] ?? null,
                'carrera_primera_id'   => $data['carrera_primera_id'],
                'carrera_segunda_id'   => $data['carrera_segunda_id'] ?? null,
                'estado'               => EstadoPostulacion::PREINSCRITO,
                'fecha_preinscripcion' => now(),
            ]);

            BitacoraService::registrar('PREINSCRIPCION_CREADA', 'postulacion', $postulacion->id);

            return response()->json([
                'message'     => 'Preinscripcion registrada correctamente.',
                'postulacion' => $postulacion->load(['persona', 'carreraPrimera', 'carreraSegunda', 'gestion']),
            ], 201);
        });
    }

    /** CU6 - Genera el formulario de preinscripcion y deja la postulacion lista para pagar. */
    public function generarFormulario(Postulacion $postulacion): JsonResponse
    {
        if ($postulacion->estado === EstadoPostulacion::ANULADO) {
            return response()->json(['message' => 'La postulacion esta anulada.'], 422);
        }

        if ($postulacion->estado === EstadoPostulacion::PREINSCRITO) {
            $postulacion->estado = EstadoPostulacion::FORMULARIO_GENERADO;
            $postulacion->save();
        }

        // Asegurar checklist de requisitos (CU8 lo va a usar)
        RequisitoService::asegurarChecklist($postulacion);

        BitacoraService::registrar('FORMULARIO_GENERADO', 'postulacion', $postulacion->id);

        // En Ciclo 1 devolvemos el payload del formulario; el frontend lo
        // imprime con window.print(). PDF nativo en Ciclo 6.
        return response()->json([
            'message'     => 'Formulario generado.',
            'postulacion' => $postulacion->fresh()->load(['persona', 'carreraPrimera', 'carreraSegunda', 'gestion', 'colegio']),
        ]);
    }

    /** Consulta publica del estado de una preinscripcion. */
    public function show(Postulacion $postulacion): JsonResponse
    {
        return response()->json($postulacion->load(['persona', 'gestion', 'carreraPrimera', 'carreraSegunda', 'pagos']));
    }

    /**
     * Busqueda publica de postulacion por documento.
     * Usado por: reimprimir formulario y "ir a pagar" cuando el postulante
     * solo recuerda su carnet (no el id interno de la postulacion).
     */
    public function buscarPorDocumento(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tipo_documento' => ['required', Rule::in(['CI_BO', 'EXT'])],
            'documento'      => ['required', 'string', 'max:30'],
            'expedido'       => ['required_if:tipo_documento,CI_BO', 'nullable', 'string', 'max:2'],
        ]);

        $busqueda = [
            'tipo_documento' => $data['tipo_documento'],
            'documento'      => $data['documento'],
        ];
        if ($data['tipo_documento'] === 'CI_BO') {
            $busqueda['expedido'] = $data['expedido'];
        }

        $persona = Persona::where($busqueda)->first();
        if (!$persona) {
            return response()->json([
                'message' => 'No encontramos una persona con esos datos.',
            ], 404);
        }

        $gestionActiva = GestionCup::where('estado', 'ACTIVA')->latest()->first();
        if (!$gestionActiva) {
            return response()->json([
                'message' => 'No hay una gestion CUP activa actualmente.',
            ], 404);
        }

        $postulacion = Postulacion::where('persona_id', $persona->id)
            ->where('gestion_cup_id', $gestionActiva->id)
            ->first();

        if (!$postulacion) {
            return response()->json([
                'message' => 'Esta persona aun no se ha preinscrito en la gestion actual.',
            ], 404);
        }

        return response()->json([
            'postulacion' => $postulacion->load(['persona', 'gestion', 'carreraPrimera', 'carreraSegunda', 'pagos']),
        ]);
    }
}
