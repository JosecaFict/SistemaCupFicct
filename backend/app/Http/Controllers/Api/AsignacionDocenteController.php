<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ambiente;
use App\Models\AsignacionDocente;
use App\Models\GestionMateria;
use App\Models\Grupo;
use App\Models\Rol;
use App\Models\User;
use App\Services\AsignacionDocenteService;
use App\Services\BitacoraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/*
| AsignacionDocenteController (Ciclo 2 - CU12, CU13)
| --------------------------------------------------------------------------
| CRUD + endpoints auxiliares para el flujo 100% guiado de asignacion
| docente -> grupo x materia, con horario y ambiente.
|
| Acceso: ADMINISTRADOR + COORDINADOR.
|
| Endpoints principales:
|   GET    /api/asignaciones-docente                lista paginada (filtros)
|   POST   /api/asignaciones-docente                crear
|   PUT    /api/asignaciones-docente/{id}           editar
|   DELETE /api/asignaciones-docente/{id}           borrar
|
| Endpoints auxiliares para el flujo guiado:
|   GET /api/asignaciones-docente/datos-iniciales?gestion_id=X
|        -> grupos, materias y bloques horarios sugeridos.
|   GET /api/asignaciones-docente/recursos-disponibles?gestion_id=X&dias=...&hora_inicio=...&hora_fin=...
|        -> docentes y ambientes que NO chocan con la franja.
*/
class AsignacionDocenteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = AsignacionDocente::with([
            'gestion:id,codigo,nombre',
            'grupo.turno:id,codigo,nombre',
            'gestionMateria.materia:id,codigo,nombre',
            'docente:id,nombre,apellidos,email',
            'ambiente:id,nombre,ubicacion,capacidad',
        ]);

        if ($g = $request->query('gestion_id')) $q->where('gestion_cup_id', $g);
        if ($g = $request->query('grupo_id'))   $q->where('grupo_id', $g);
        if ($d = $request->query('docente_id')) $q->where('docente_user_id', $d);
        if ($t = $request->query('turno_id')) {
            $q->whereHas('grupo', fn ($pp) => $pp->where('turno_id', $t));
        }
        if ($m = $request->query('materia_id')) {
            $q->whereHas('gestionMateria', fn ($pp) => $pp->where('materia_id', $m));
        }

        return response()->json($q->orderBy('grupo_id')->orderBy('hora_inicio')->paginate(50));
    }

    /**
     * Catalogos para los filtros de la lista: turnos habilitados, materias
     * de la gestion y docentes que ya tienen asignaciones en la gestion.
     */
    public function catalogosFiltros(Request $request): JsonResponse
    {
        $request->validate(['gestion_id' => ['required', 'exists:gestiones_cup,id']]);
        $gestionId = (int) $request->query('gestion_id');

        $turnos = \App\Models\Turno::where('activo', true)
            ->orderBy('id')
            ->select('id', 'codigo', 'nombre')
            ->get();

        $materias = GestionMateria::where('gestion_cup_id', $gestionId)
            ->with('materia:id,codigo,nombre')
            ->select('id', 'materia_id', 'gestion_cup_id')
            ->get()
            ->map(fn ($gm) => [
                'gestion_materia_id' => $gm->id,
                'materia_id'         => $gm->materia_id,
                'codigo'             => $gm->materia?->codigo,
                'nombre'             => $gm->materia?->nombre,
            ]);

        // Docentes que tienen al menos una asignacion en esta gestion
        $docenteIds = AsignacionDocente::where('gestion_cup_id', $gestionId)
            ->pluck('docente_user_id')
            ->unique()
            ->all();

        $docentes = User::whereIn('id', $docenteIds)
            ->orderBy('apellidos')
            ->select('id', 'nombre', 'apellidos', 'email')
            ->get();

        return response()->json([
            'turnos'   => $turnos,
            'materias' => $materias,
            'docentes' => $docentes,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validar($request);

        // Sanity check: la gestion_materia debe pertenecer a la misma gestion
        $gm = GestionMateria::findOrFail($data['gestion_materia_id']);
        abort_if($gm->gestion_cup_id !== (int) $data['gestion_cup_id'], 422,
            'La materia no pertenece a la gestion seleccionada.');

        // Sanity check: el grupo debe pertenecer a la misma gestion
        $grupo = Grupo::findOrFail($data['grupo_id']);
        abort_if($grupo->gestion_cup_id !== (int) $data['gestion_cup_id'], 422,
            'El grupo no pertenece a la gestion seleccionada.');

        // El docente debe tener rol DOCENTE
        $this->verificarRolDocente($data['docente_user_id']);

        // Ciclo 3: habilitacion + carga maxima
        $this->verificarHabilitacion($data['docente_user_id'], (int) $gm->materia_id);
        $this->verificarCargaMaxima($data['docente_user_id'], (int) $data['gestion_cup_id']);

        $conflictos = AsignacionDocenteService::detectarConflictos($data['gestion_cup_id'], $data);
        if (!empty($conflictos)) {
            throw ValidationException::withMessages([
                'conflicto' => $this->mensajesConflictos($conflictos),
            ]);
        }

        $asignacion = AsignacionDocente::create($data);
        BitacoraService::registrar('ASIGNACION_DOCENTE_CREADA', 'asignacion_docente', $asignacion->id, $data);

        return response()->json($this->cargarRelaciones($asignacion), 201);
    }

    public function update(Request $request, AsignacionDocente $asignacion): JsonResponse
    {
        $data = $this->validar($request);
        $this->verificarRolDocente($data['docente_user_id']);

        // Ciclo 3: habilitacion siempre; carga maxima solo si CAMBIA el docente
        // (si es el mismo docente, no se suma carga adicional).
        $gm = GestionMateria::findOrFail($data['gestion_materia_id']);
        $this->verificarHabilitacion($data['docente_user_id'], (int) $gm->materia_id);
        if ((int) $data['docente_user_id'] !== (int) $asignacion->docente_user_id) {
            $this->verificarCargaMaxima($data['docente_user_id'], (int) $data['gestion_cup_id']);
        }

        $conflictos = AsignacionDocenteService::detectarConflictos($data['gestion_cup_id'], $data, $asignacion->id);
        if (!empty($conflictos)) {
            throw ValidationException::withMessages([
                'conflicto' => $this->mensajesConflictos($conflictos),
            ]);
        }

        $asignacion->update($data);
        BitacoraService::registrar('ASIGNACION_DOCENTE_MODIFICADA', 'asignacion_docente', $asignacion->id, $data);

        return response()->json($this->cargarRelaciones($asignacion));
    }

    public function destroy(AsignacionDocente $asignacion): JsonResponse
    {
        $id = $asignacion->id;
        $asignacion->delete();
        BitacoraService::registrar('ASIGNACION_DOCENTE_ELIMINADA', 'asignacion_docente', $id);
        return response()->json(['ok' => true]);
    }

    /**
     * Datos iniciales para el formulario guiado:
     *   - grupos activos de la gestion
     *   - gestion_materias de la gestion (con su materia)
     *   - bloques horarios sugeridos por turno
     */
    public function datosIniciales(Request $request): JsonResponse
    {
        $request->validate(['gestion_id' => ['required', 'exists:gestiones_cup,id']]);
        $gestionId = (int) $request->query('gestion_id');

        $grupos = Grupo::where('gestion_cup_id', $gestionId)
            ->where('estado', 'ACTIVO')
            ->with('turno:id,codigo,nombre')
            ->select('id', 'gestion_cup_id', 'turno_id', 'ambiente_id', 'codigo', 'capacidad', 'inscritos_actuales')
            ->orderBy('codigo')
            ->get();

        $materias = GestionMateria::where('gestion_cup_id', $gestionId)
            ->with('materia:id,codigo,nombre')
            ->select('id', 'gestion_cup_id', 'materia_id', 'ponderacion')
            ->get();

        // Bloques sugeridos por turno (08-10/10-12, 13-15/15-17, 18-20/20-22)
        $bloquesPorTurno = [
            'M' => [['08:00', '10:00'], ['10:00', '12:00']],
            'T' => [['13:00', '15:00'], ['15:00', '17:00']],
            'N' => [['18:00', '20:00'], ['20:00', '22:00']],
        ];

        $patronesDias = [
            'LU,MI,VI' => 'Lunes, Miercoles y Viernes',
            'MA,JU,SA' => 'Martes, Jueves y Sabado',
        ];

        return response()->json([
            'grupos'           => $grupos,
            'gestion_materias' => $materias,
            'bloques_por_turno'=> $bloquesPorTurno,
            'patrones_dias'    => $patronesDias,
        ]);
    }

    /**
     * Para una franja (gestion + dias + horario), devuelve:
     *  - docentes_disponibles: users con rol DOCENTE que NO chocan
     *  - ambientes_disponibles: ambientes activos que NO chocan
     *  - materias_ya_asignadas_al_grupo: materias del grupo ya asignadas
     *    (para deshabilitar en el form si se manda grupo_id)
     */
    public function recursosDisponibles(Request $request): JsonResponse
    {
        $data = $request->validate([
            'gestion_id'         => ['required', 'exists:gestiones_cup,id'],
            'dias_semana'        => ['required', 'string', 'regex:/^[A-Z,]+$/'],
            'hora_inicio'        => ['required', 'date_format:H:i'],
            'hora_fin'           => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'grupo_id'           => ['nullable', 'integer'],
            'gestion_materia_id' => ['nullable', 'integer', 'exists:gestion_materias,id'],
            'ignore_id'          => ['nullable', 'integer'], // para edicion
        ]);

        $gestionId = (int) $data['gestion_id'];
        $ignoreId  = isset($data['ignore_id']) ? (int) $data['ignore_id'] : null;

        $docentesOcupados   = AsignacionDocenteService::docentesOcupados($gestionId, $data['dias_semana'], $data['hora_inicio'], $data['hora_fin'], $ignoreId);
        $ambientesOcupados  = AsignacionDocenteService::ambientesOcupados($gestionId, $data['dias_semana'], $data['hora_inicio'], $data['hora_fin'], $ignoreId);
        $gruposOcupados     = AsignacionDocenteService::gruposOcupados($gestionId, $data['dias_semana'], $data['hora_inicio'], $data['hora_fin'], $ignoreId);

        // Ciclo 3: exclusiones por reglas de habilitacion + carga maxima
        $docentesAlMaximo = AsignacionDocenteService::docentesAlMaximo($gestionId, $ignoreId);
        $contadoresDocentes = AsignacionDocenteService::contadoresPorDocente($gestionId, $ignoreId);
        $maxAsignaciones = (int) config('cup.MAX_ASIGNACIONES_DOCENTE', 4);

        $rolDocenteId = Rol::where('codigo', 'DOCENTE')->value('id');
        $docentesQuery = User::where('role_id', $rolDocenteId)
            ->where('activo', true)
            ->whereNotIn('id', $docentesOcupados)
            ->whereNotIn('id', $docentesAlMaximo);

        // Si el frontend manda la materia, filtramos por docentes habilitados.
        // Si no la manda (retrocompat), devolvemos todos los que quedan.
        if (!empty($data['gestion_materia_id'])
            && config('cup.BLOQUEO_ESTRICTO_HABILITACION', true)) {
            $materiaId = GestionMateria::whereKey($data['gestion_materia_id'])->value('materia_id');
            if ($materiaId) {
                $docentesQuery->whereHas('materiasHabilitadas',
                    fn ($q) => $q->where('materias.id', $materiaId)
                );
            }
        }

        $docentes = $docentesQuery
            ->select('id', 'nombre', 'apellidos', 'email')
            ->orderBy('apellidos')
            ->get()
            ->map(function ($u) use ($contadoresDocentes, $maxAsignaciones) {
                $usados = (int) ($contadoresDocentes[$u->id] ?? 0);
                $u->asignaciones_usadas = $usados;
                $u->asignaciones_maximo = $maxAsignaciones;
                return $u;
            });

        $ambientes = Ambiente::where('activo', true)
            ->whereNotIn('id', $ambientesOcupados)
            ->select('id', 'nombre', 'ubicacion', 'capacidad')
            ->orderBy('nombre')
            ->get();

        // Materias ya asignadas al grupo (si se especifica)
        $materiasYaAsignadas = [];
        if (!empty($data['grupo_id'])) {
            $materiasYaAsignadas = AsignacionDocente::where('grupo_id', $data['grupo_id'])
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->pluck('gestion_materia_id')
                ->all();
        }

        return response()->json([
            'docentes_disponibles'           => $docentes,
            'ambientes_disponibles'          => $ambientes,
            'grupos_ocupados_en_franja'      => $gruposOcupados,
            'materias_ya_asignadas_al_grupo' => $materiasYaAsignadas,
            'max_asignaciones_docente'       => $maxAsignaciones,
        ]);
    }

    // ----------------------------------------------------------------------

    private function validar(Request $request): array
    {
        return $request->validate([
            'gestion_cup_id'     => ['required', 'exists:gestiones_cup,id'],
            'grupo_id'           => ['required', 'exists:grupos,id'],
            'gestion_materia_id' => ['required', 'exists:gestion_materias,id'],
            'docente_user_id'    => ['required', 'exists:users,id'],
            'ambiente_id'        => ['nullable', 'exists:ambientes,id'],
            'dias_semana'        => ['required', 'string', 'regex:/^[A-Z]{2}(,[A-Z]{2})*$/'],
            'hora_inicio'        => ['required', 'date_format:H:i'],
            'hora_fin'           => ['required', 'date_format:H:i', 'after:hora_inicio'],
        ]);
    }

    private function verificarRolDocente(int $userId): void
    {
        $user = User::with('rol:id,codigo')->find($userId);
        abort_if(!$user || !$user->activo, 422, 'El usuario seleccionado no esta activo.');
        abort_if($user->rol?->codigo !== 'DOCENTE', 422,
            'El usuario seleccionado no tiene rol DOCENTE.');
    }

    /**
     * Ciclo 3: verifica que el docente este habilitado en la materia.
     * Si BLOQUEO_ESTRICTO_HABILITACION es false, solo se registra warning.
     */
    private function verificarHabilitacion(int $docenteUserId, int $materiaId): void
    {
        if (!config('cup.BLOQUEO_ESTRICTO_HABILITACION', true)) {
            return;
        }
        if (!AsignacionDocenteService::estaHabilitado($docenteUserId, $materiaId)) {
            abort(422, 'El docente no esta habilitado para dar esta materia. '
                . 'Edite su perfil para habilitarla o elija otro docente.');
        }
    }

    /**
     * Ciclo 3: verifica que el docente no supere MAX_ASIGNACIONES_DOCENTE
     * en la gestion. En edicion, no cuenta la propia fila.
     */
    private function verificarCargaMaxima(int $docenteUserId, int $gestionCupId, ?int $ignoreId = null): void
    {
        $max = (int) config('cup.MAX_ASIGNACIONES_DOCENTE', 4);
        $actual = AsignacionDocenteService::contarAsignacionesActivas($docenteUserId, $gestionCupId, $ignoreId);
        abort_if($actual >= $max, 422,
            "El docente ya tiene {$actual} asignaciones en esta gestion (maximo permitido: {$max})."
        );
    }

    private function mensajesConflictos(array $conflictos): array
    {
        $map = [
            'docente_ocupado'  => 'El docente ya tiene otra clase en esos dias y horario.',
            'grupo_ocupado'    => 'El grupo ya tiene otra materia asignada en esos dias y horario.',
            'ambiente_ocupado' => 'El ambiente ya esta ocupado en esos dias y horario.',
        ];
        return array_map(fn ($c) => $map[$c] ?? $c, $conflictos);
    }

    private function cargarRelaciones(AsignacionDocente $a): AsignacionDocente
    {
        return $a->fresh()->load([
            'gestion:id,codigo,nombre',
            'grupo.turno:id,codigo,nombre',
            'gestionMateria.materia:id,codigo,nombre',
            'docente:id,nombre,apellidos,email',
            'ambiente:id,nombre,ubicacion,capacidad',
        ]);
    }
}
