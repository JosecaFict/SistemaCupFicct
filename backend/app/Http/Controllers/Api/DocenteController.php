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
use App\Services\ExcelExport;
use App\Services\ExcelImport;
use App\Services\NotaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

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

        $res = $this->aplicarNotas(
            $data['notas'], $idsValidos, $gestion,
            $data['gestion_materia_id'], $data['numero_examen'], $userId
        );

        BitacoraService::registrar('NOTAS_CARGADAS', 'asignacion_docente', $asignacion->id, [
            'examen'       => $data['numero_examen'],
            'insertados'   => $res['insertados'],
            'actualizados' => $res['actualizados'],
            'bloqueados'   => count($res['bloqueados']),
        ]);

        return response()->json([
            'insertados'   => $res['insertados'],
            'actualizados' => $res['actualizados'],
            'bloqueados_validados' => $res['bloqueados'],
            'mensaje' => 'Notas guardadas en estado PENDIENTE de validacion.',
        ]);
    }

    /*
    | CU17 - Plantillas Excel de notas
    | ----------------------------------------------------------------------
    | El docente descarga una plantilla .xlsx con sus alumnos (codigo + nombre)
    | y una columna 'Nota' vacia; la llena y la sube para cargar en bloque.
    */

    /** Descarga la plantilla .xlsx del grupo/materia/examen. */
    public function plantillaNotas(Request $request): Response
    {
        $data = $request->validate([
            'grupo_id'           => ['required', 'exists:grupos,id'],
            'gestion_materia_id' => ['required', 'exists:gestion_materias,id'],
            'numero_examen'      => ['required', 'integer', 'min:1', 'max:3'],
        ]);

        $this->asignacionDelDocente($data['grupo_id'], $data['gestion_materia_id'], Auth::id());
        $grupo = Grupo::findOrFail($data['grupo_id']);

        $postulantes = Postulacion::with('persona:id,nombre,apellido_paterno,apellido_materno')
            ->where('grupo_id', $grupo->id)
            ->where('gestion_cup_id', $grupo->gestion_cup_id)
            ->orderBy('codigo_postulante')
            ->get(['id', 'persona_id', 'codigo_postulante']);

        $matrix = [['Codigo', 'Nombre', 'Nota']];
        foreach ($postulantes as $p) {
            $matrix[] = [
                $p->codigo_postulante,
                trim(($p->persona?->nombre ?? '') . ' ' . ($p->persona?->apellido_paterno ?? '') . ' ' . ($p->persona?->apellido_materno ?? '')),
                null,
            ];
        }

        $gm = GestionMateria::with('materia')->find($data['gestion_materia_id']);
        $nombre = "plantilla-notas-{$grupo->codigo}-" . ($gm?->materia?->codigo ?? 'MAT') . "-ex{$data['numero_examen']}";

        return ExcelExport::stream($nombre, $matrix);
    }

    /** Importa notas desde un .xlsx (columna A=Codigo, C=Nota). */
    public function importarNotas(Request $request): JsonResponse
    {
        $data = $request->validate([
            'grupo_id'           => ['required', 'exists:grupos,id'],
            'gestion_materia_id' => ['required', 'exists:gestion_materias,id'],
            'numero_examen'      => ['required', 'integer', 'min:1', 'max:3'],
            'archivo'            => ['required', 'file', 'mimes:xlsx', 'max:5120'],
        ]);

        $userId = Auth::id();
        $asignacion = $this->asignacionDelDocente($data['grupo_id'], $data['gestion_materia_id'], $userId);
        $gestion = GestionCup::findOrFail($asignacion->gestion_cup_id);

        // codigo_postulante -> postulacion_id del grupo
        $porCodigo = Postulacion::where('grupo_id', $data['grupo_id'])
            ->where('gestion_cup_id', $gestion->id)
            ->whereNotNull('codigo_postulante')
            ->pluck('id', 'codigo_postulante');
        $idsValidos = $porCodigo->values()->all();

        try {
            $filas = ExcelImport::rows($request->file('archivo')->getRealPath());
        } catch (\Throwable $e) {
            return response()->json(['message' => 'No se pudo leer el Excel: ' . $e->getMessage()], 422);
        }

        $items = [];
        $ignoradas = 0;
        foreach ($filas as $i => $fila) {
            if ($i === 0) {
                continue; // encabezado
            }
            $codigo  = isset($fila[0]) ? trim((string) $fila[0]) : '';
            $notaRaw = $fila[2] ?? null;
            if ($codigo === '' || $notaRaw === null || trim((string) $notaRaw) === '') {
                continue; // fila vacia, no cuenta como error
            }

            $notaStr = str_replace(',', '.', trim((string) $notaRaw));
            $pid = $porCodigo[$codigo] ?? null;
            if (!$pid || !is_numeric($notaStr) || (float) $notaStr < 0 || (float) $notaStr > 100) {
                $ignoradas++;
                continue;
            }
            $items[] = ['postulacion_id' => $pid, 'valor' => (float) $notaStr];
        }

        if (empty($items)) {
            return response()->json(['message' => 'El Excel no tiene notas validas para cargar.'], 422);
        }

        $res = $this->aplicarNotas(
            $items, $idsValidos, $gestion,
            $data['gestion_materia_id'], $data['numero_examen'], $userId
        );

        BitacoraService::registrar('NOTAS_CARGADAS_EXCEL', 'asignacion_docente', $asignacion->id, [
            'examen'       => $data['numero_examen'],
            'insertados'   => $res['insertados'],
            'actualizados' => $res['actualizados'],
            'bloqueados'   => count($res['bloqueados']),
            'ignoradas'    => $ignoradas,
        ]);

        return response()->json([
            'insertados'   => $res['insertados'],
            'actualizados' => $res['actualizados'],
            'bloqueados_validados' => $res['bloqueados'],
            'ignoradas'    => $ignoradas,
            'mensaje' => 'Notas importadas desde Excel en estado PENDIENTE de validacion.',
        ]);
    }

    /** Asignacion del docente a (grupo x materia), o 403. */
    private function asignacionDelDocente(int $grupoId, int $gmId, int $userId): AsignacionDocente
    {
        $asignacion = AsignacionDocente::where('grupo_id', $grupoId)
            ->where('gestion_materia_id', $gmId)
            ->where('docente_user_id', $userId)
            ->first();
        abort_if(!$asignacion, 403, 'No estas asignado a este grupo y materia.');
        return $asignacion;
    }

    /**
     * Persiste un lote de notas [{postulacion_id, valor}] para un examen.
     * Reutilizado por la carga web (guardarNotas) y por Excel (importarNotas).
     * Las VALIDADAS no se tocan (quedan en 'bloqueados').
     */
    private function aplicarNotas(array $items, array $idsValidos, GestionCup $gestion, int $gestionMateriaId, int $numeroExamen, int $userId): array
    {
        $insertados = 0;
        $actualizados = 0;
        $bloqueados = [];

        DB::transaction(function () use ($items, $idsValidos, $gestion, $gestionMateriaId, $numeroExamen, $userId, &$insertados, &$actualizados, &$bloqueados) {
            foreach ($items as $item) {
                $pid = (int) $item['postulacion_id'];
                if (!in_array($pid, $idsValidos, true)) {
                    continue;
                }
                $existente = Nota::where('postulacion_id', $pid)
                    ->where('gestion_materia_id', $gestionMateriaId)
                    ->where('numero_examen', $numeroExamen)
                    ->first();

                $valor = round((float) $item['valor'], 2);
                $descalifica = NotaService::calcularDescalifica($gestion, $valor);

                if ($existente) {
                    if ($existente->estado === EstadoNota::VALIDADA) {
                        $bloqueados[] = $pid;
                        continue;
                    }
                    $existente->update([
                        'valor'                 => $valor,
                        'docente_user_id'       => $userId,
                        'estado'                => EstadoNota::PENDIENTE,
                        'descalifica'           => $descalifica,
                        'observacion'           => null,
                        'validado_por_user_id'  => null,
                        'fecha_validacion'      => null,
                    ]);
                    $actualizados++;
                } else {
                    Nota::create([
                        'postulacion_id'     => $pid,
                        'gestion_materia_id' => $gestionMateriaId,
                        'numero_examen'      => $numeroExamen,
                        'valor'              => $valor,
                        'docente_user_id'    => $userId,
                        'estado'             => EstadoNota::PENDIENTE,
                        'descalifica'        => $descalifica,
                    ]);
                    $insertados++;
                }
            }
        });

        return ['insertados' => $insertados, 'actualizados' => $actualizados, 'bloqueados' => $bloqueados];
    }
}
