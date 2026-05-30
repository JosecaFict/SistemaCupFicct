<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CupoCarrera;
use App\Models\FechaExamen;
use App\Models\GestionCup;
use App\Models\GestionMateria;
use App\Services\GrupoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/*
| GestionCupController (CU3)
| --------------------------------------------------------------------------
| CRUD de gestiones CUP. Una gestion guarda fechas, ponderaciones, cupos por
| carrera, capacidad maxima por grupo, estimado de postulantes y turnos.
|
| POST /api/admin/gestiones/{id}/generar-grupos -> dispara GrupoService (CU11).
*/
class GestionCupController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            GestionCup::orderByDesc('id')->paginate(20)
        );
    }

    public function show(GestionCup $gestion): JsonResponse
    {
        return response()->json(
            $gestion->load(['fechasExamenes', 'gestionMaterias.materia', 'cuposCarrera.carrera', 'grupos.turno'])
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validar($request);
        return DB::transaction(function () use ($data) {
            $gestion = GestionCup::create([
                'codigo'                       => $data['codigo'],
                'nombre'                       => $data['nombre'],
                'fecha_inicio_preinscripcion'  => $data['fecha_inicio_preinscripcion'],
                'fecha_cierre_preinscripcion'  => $data['fecha_cierre_preinscripcion'],
                'cantidad_examenes'            => $data['cantidad_examenes'],
                'capacidad_maxima_grupo'       => $data['capacidad_maxima_grupo'],
                'estimado_postulantes'         => $data['estimado_postulantes'],
                'turnos_habilitados'           => $data['turnos_habilitados'],
                'estado'                       => $data['estado'] ?? 'BORRADOR',
            ]);

            $this->syncRelaciones($gestion, $data);

            return response()->json($gestion->fresh()->load([
                'fechasExamenes', 'gestionMaterias.materia', 'cuposCarrera.carrera',
            ]), 201);
        });
    }

    public function update(Request $request, GestionCup $gestion): JsonResponse
    {
        $data = $this->validar($request, $gestion->id);
        return DB::transaction(function () use ($data, $gestion) {
            $gestion->update([
                'codigo'                       => $data['codigo'],
                'nombre'                       => $data['nombre'],
                'fecha_inicio_preinscripcion'  => $data['fecha_inicio_preinscripcion'],
                'fecha_cierre_preinscripcion'  => $data['fecha_cierre_preinscripcion'],
                'cantidad_examenes'            => $data['cantidad_examenes'],
                'capacidad_maxima_grupo'       => $data['capacidad_maxima_grupo'],
                'estimado_postulantes'         => $data['estimado_postulantes'],
                'turnos_habilitados'           => $data['turnos_habilitados'],
                'estado'                       => $data['estado'] ?? $gestion->estado,
            ]);

            // Reemplazar relaciones detalle
            $gestion->fechasExamenes()->delete();
            $gestion->gestionMaterias()->delete();
            $gestion->cuposCarrera()->delete();
            $this->syncRelaciones($gestion, $data);

            return response()->json($gestion->fresh()->load([
                'fechasExamenes', 'gestionMaterias.materia', 'cuposCarrera.carrera',
            ]));
        });
    }

    /** Endpoint para generar grupos (CU11). */
    public function generarGrupos(Request $request, GestionCup $gestion): JsonResponse
    {
        $forzar = (bool) $request->boolean('forzar');
        $resumen = GrupoService::generarParaGestion($gestion, $forzar);

        return response()->json([
            'message' => 'Grupos generados correctamente.',
            'resumen' => $resumen,
            'grupos'  => $gestion->grupos()->with('turno')->orderBy('codigo')->get(),
        ]);
    }

    /** Validacion comun de gestion. */
    private function validar(Request $request, ?int $gestionId = null): array
    {
        $unique = $gestionId
            ? \Illuminate\Validation\Rule::unique('gestiones_cup', 'codigo')->ignore($gestionId)
            : 'unique:gestiones_cup,codigo';

        $data = $request->validate([
            'codigo'                       => ['required', 'string', 'max:20', $unique],
            'nombre'                       => ['required', 'string', 'max:120'],
            'fecha_inicio_preinscripcion'  => ['required', 'date'],
            'fecha_cierre_preinscripcion'  => ['required', 'date', 'after_or_equal:fecha_inicio_preinscripcion'],
            'cantidad_examenes'            => ['required', 'integer', 'in:2,3'],
            'capacidad_maxima_grupo'       => ['required', 'integer', 'min:1', 'max:200'],
            'estimado_postulantes'         => ['required', 'integer', 'min:1'],
            'turnos_habilitados'           => ['required', 'string', 'regex:/^([MTN])(,[MTN])*$/'],
            'estado'                       => ['nullable', 'in:BORRADOR,ACTIVA,CERRADA'],

            'fechas_examenes'              => ['array'],
            'fechas_examenes.*.numero'     => ['required_with:fechas_examenes', 'integer', 'min:1', 'max:3'],
            'fechas_examenes.*.fecha'      => ['required_with:fechas_examenes', 'date'],
            'fechas_examenes.*.ponderacion'=> ['required_with:fechas_examenes', 'integer', 'min:0', 'max:100'],

            'materias'                     => ['array'],
            'materias.*.materia_id'        => ['required_with:materias', 'exists:materias,id'],
            'materias.*.ponderacion'       => ['required_with:materias', 'integer', 'min:0', 'max:100'],

            'cupos_carrera'                => ['array'],
            'cupos_carrera.*.carrera_id'   => ['required_with:cupos_carrera', 'exists:carreras,id'],
            'cupos_carrera.*.cupos'        => ['required_with:cupos_carrera', 'integer', 'min:0'],
        ]);

        // Validacion adicional: suma de ponderaciones = 100
        if (!empty($data['fechas_examenes'])) {
            $suma = array_sum(array_column($data['fechas_examenes'], 'ponderacion'));
            abort_if($suma !== 100, 422, 'La suma de ponderaciones de examenes debe ser 100.');
        }
        if (!empty($data['materias'])) {
            $suma = array_sum(array_column($data['materias'], 'ponderacion'));
            abort_if($suma !== 100, 422, 'La suma de ponderaciones de materias debe ser 100.');
        }

        return $data;
    }

    private function syncRelaciones(GestionCup $gestion, array $data): void
    {
        foreach ($data['fechas_examenes'] ?? [] as $fe) {
            FechaExamen::create([
                'gestion_cup_id' => $gestion->id,
                'numero'         => $fe['numero'],
                'fecha'          => $fe['fecha'],
                'ponderacion'    => $fe['ponderacion'],
            ]);
        }
        foreach ($data['materias'] ?? [] as $m) {
            GestionMateria::create([
                'gestion_cup_id' => $gestion->id,
                'materia_id'     => $m['materia_id'],
                'ponderacion'    => $m['ponderacion'],
            ]);
        }
        foreach ($data['cupos_carrera'] ?? [] as $c) {
            CupoCarrera::create([
                'gestion_cup_id' => $gestion->id,
                'carrera_id'     => $c['carrera_id'],
                'cupos'          => $c['cupos'],
            ]);
        }
    }
}
