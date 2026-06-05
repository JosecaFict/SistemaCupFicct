<?php

namespace App\Http\Controllers\Api;

use App\Enums\EstadoNota;
use App\Http\Controllers\Controller;
use App\Models\AsignacionDocente;
use App\Models\GestionCup;
use App\Models\GestionMateria;
use App\Models\Grupo;
use App\Models\Nota;
use App\Models\Postulacion;
use App\Services\BitacoraService;
use App\Services\NotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/*
| DocenteController (Ciclo 2 - CU14)
| --------------------------------------------------------------------------
| Endpoints para el docente:
|   GET  /api/docente/mis-asignaciones
|        -> lista las asignaciones del docente autenticado (puede filtrar
|           por gestion_id).
|   GET  /api/docente/grupo/{grupo}/materia/{gm}/examen/{n}
|        -> tabla de postulantes del grupo + sus notas para ese examen.
|   POST /api/docente/notas/guardar
|        -> guarda/actualiza un lote de notas (siempre quedan PENDIENTE).
*/
class DocenteController extends Controller
{
    public function misAsignaciones(Request $request): JsonResponse
    {
        $userId = Auth::id();

        $q = AsignacionDocente::with([
            'gestion:id,codigo,nombre,cantidad_examenes,nota_minima_aprobacion',
            'grupo.turno:id,codigo,nombre',
            'gestionMateria.materia:id,codigo,nombre',
            'ambiente:id,nombre,ubicacion',
        ])->where('docente_user_id', $userId);

        if ($gid = $request->query('gestion_id')) {
            $q->where('gestion_cup_id', $gid);
        }

        $asignaciones = $q->orderBy('grupo_id')->orderBy('hora_inicio')->get();

        // Para cada asignacion: cuantas notas ya cargadas vs total esperado
        $datos = $asignaciones->map(function ($a) {
            $totalPostulantes = Postulacion::where('grupo_id', $a->grupo_id)
                ->where('gestion_cup_id', $a->gestion_cup_id)
                ->count();
            $numExamenes = (int) ($a->gestion?->cantidad_examenes ?? 0);

            // Notas cargadas por examen
            $cargadasPorExamen = Nota::whereIn('postulacion_id',
                    Postulacion::where('grupo_id', $a->grupo_id)
                        ->where('gestion_cup_id', $a->gestion_cup_id)
                        ->pluck('id'))
                ->where('gestion_materia_id', $a->gestion_materia_id)
                ->select('numero_examen', DB::raw('COUNT(*) as cant'),
                    DB::raw("COUNT(*) FILTER (WHERE estado='PENDIENTE') as pendientes"),
                    DB::raw("COUNT(*) FILTER (WHERE estado='VALIDADA') as validadas"),
                    DB::raw("COUNT(*) FILTER (WHERE estado='RECHAZADA') as rechazadas"))
                ->groupBy('numero_examen')
                ->get()
                ->keyBy('numero_examen');

            return [
                'id'             => $a->id,
                'gestion'        => $a->gestion,
                'grupo'          => $a->grupo,
                'gestion_materia'=> $a->gestionMateria,
                'ambiente'       => $a->ambiente,
                'dias_semana'    => $a->dias_semana,
                'hora_inicio'    => $a->hora_inicio,
                'hora_fin'       => $a->hora_fin,
                'total_postulantes' => $totalPostulantes,
                'numero_examenes'   => $numExamenes,
                'progreso'          => $cargadasPorExamen,
            ];
        });

        return response()->json($datos);
    }

    /**
     * Devuelve postulantes del grupo + notas existentes para una asignacion
     * y un examen especifico.
     */
    public function notasDelExamen(Request $request, Grupo $grupo, GestionMateria $gm, int $examen): JsonResponse
    {
        abort_if($examen < 1 || $examen > 3, 422, 'Numero de examen invalido.');

        $userId = Auth::id();

        // Verificar que el docente este asignado a este grupo+materia
        $asignacion = AsignacionDocente::where('grupo_id', $grupo->id)
            ->where('gestion_materia_id', $gm->id)
            ->where('docente_user_id', $userId)
            ->first();
        abort_if(!$asignacion, 403, 'No estas asignado a este grupo y materia.');

        $postulantes = Postulacion::with('persona:id,nombre,apellido_paterno,apellido_materno,documento')
            ->where('grupo_id', $grupo->id)
            ->where('gestion_cup_id', $grupo->gestion_cup_id)
            ->orderBy('codigo_postulante')
            ->get(['id', 'persona_id', 'codigo_postulante']);

        $notasExistentes = Nota::whereIn('postulacion_id', $postulantes->pluck('id'))
            ->where('gestion_materia_id', $gm->id)
            ->where('numero_examen', $examen)
            ->get()
            ->keyBy('postulacion_id');

        $filas = $postulantes->map(function ($p, $idx) use ($notasExistentes) {
            $nota = $notasExistentes->get($p->id);
            $persona = $p->persona;
            return [
                'numero'             => $idx + 1,
                'postulacion_id'     => $p->id,
                'codigo_postulante'  => $p->codigo_postulante,
                'nombre_completo'    => trim(
                    ($persona?->nombre ?? '') . ' ' .
                    ($persona?->apellido_paterno ?? '') . ' ' .
                    ($persona?->apellido_materno ?? '')
                ),
                'documento'          => $persona?->documento,
                'nota_id'            => $nota?->id,
                'valor'              => $nota ? (float) $nota->valor : null,
                'estado'             => $nota?->estado?->value,
                'descalifica'        => $nota?->descalifica ?? false,
                'observacion'        => $nota?->observacion,
            ];
        });

        return response()->json([
            'asignacion'  => $asignacion->load(['grupo.turno', 'gestionMateria.materia', 'gestion']),
            'examen'      => $examen,
            'postulantes' => $filas,
        ]);
    }

    /**
     * Guarda o actualiza un lote de notas para un examen.
     * Solo permite editar notas en estado PENDIENTE o RECHAZADA.
     * Las VALIDADAS no pueden modificarse por el docente (solo ADMIN).
     */
    public function guardarNotas(Request $request): JsonResponse
    {
        $data = $request->validate([
            'grupo_id'             => ['required', 'exists:grupos,id'],
            'gestion_materia_id'   => ['required', 'exists:gestion_materias,id'],
            'numero_examen'        => ['required', 'integer', 'min:1', 'max:3'],
            'notas'                => ['required', 'array', 'min:1'],
            'notas.*.postulacion_id' => ['required', 'integer'],
            'notas.*.valor'          => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $userId = Auth::id();

        // Verificar asignacion del docente
        $asignacion = AsignacionDocente::where('grupo_id', $data['grupo_id'])
            ->where('gestion_materia_id', $data['gestion_materia_id'])
            ->where('docente_user_id', $userId)
            ->first();
        abort_if(!$asignacion, 403, 'No estas asignado a este grupo y materia.');

        $gestion = GestionCup::findOrFail($asignacion->gestion_cup_id);

        // Postulantes validos del grupo
        $idsValidos = Postulacion::where('grupo_id', $data['grupo_id'])
            ->where('gestion_cup_id', $gestion->id)
            ->pluck('id')
            ->all();

        $insertados = 0;
        $actualizados = 0;
        $bloqueados = [];

        DB::transaction(function () use ($data, $gestion, $userId, $idsValidos, &$insertados, &$actualizados, &$bloqueados) {
            foreach ($data['notas'] as $item) {
                if (!in_array($item['postulacion_id'], $idsValidos, true)) {
                    continue;
                }
                $existente = Nota::where('postulacion_id', $item['postulacion_id'])
                    ->where('gestion_materia_id', $data['gestion_materia_id'])
                    ->where('numero_examen', $data['numero_examen'])
                    ->first();

                $valor = round((float) $item['valor'], 2);
                $descalifica = NotaService::calcularDescalifica($gestion, $valor);

                if ($existente) {
                    // Si esta VALIDADA, el docente no puede modificar (solo ADMIN)
                    if ($existente->estado === EstadoNota::VALIDADA) {
                        $bloqueados[] = $item['postulacion_id'];
                        continue;
                    }
                    $existente->update([
                        'valor'        => $valor,
                        'docente_user_id' => $userId,
                        'estado'       => EstadoNota::PENDIENTE,
                        'descalifica'  => $descalifica,
                        'observacion'  => null,
                        'validado_por_user_id' => null,
                        'fecha_validacion'     => null,
                    ]);
                    $actualizados++;
                } else {
                    Nota::create([
                        'postulacion_id'     => $item['postulacion_id'],
                        'gestion_materia_id' => $data['gestion_materia_id'],
                        'numero_examen'      => $data['numero_examen'],
                        'valor'              => $valor,
                        'docente_user_id'    => $userId,
                        'estado'             => EstadoNota::PENDIENTE,
                        'descalifica'        => $descalifica,
                    ]);
                    $insertados++;
                }
            }
        });

        BitacoraService::registrar('NOTAS_CARGADAS', 'asignacion_docente', $asignacion->id, [
            'examen'       => $data['numero_examen'],
            'insertados'   => $insertados,
            'actualizados' => $actualizados,
            'bloqueados'   => count($bloqueados),
        ]);

        return response()->json([
            'insertados'   => $insertados,
            'actualizados' => $actualizados,
            'bloqueados_validados' => $bloqueados,
            'mensaje' => 'Notas guardadas en estado PENDIENTE de validacion.',
        ]);
    }
}
