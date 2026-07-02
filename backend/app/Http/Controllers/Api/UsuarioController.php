<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Materia;
use App\Models\Rol;
use App\Models\User;
use App\Services\AsignacionDocenteService;
use App\Services\BitacoraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

/*
| UsuarioController (CU2 - Ciclo 3)
| --------------------------------------------------------------------------
| CRUD de usuarios con login. Solo ADMINISTRADOR.
| Activacion/inactivacion via PATCH /toggle-activo.
|
| Ciclo 3: cuando el rol es DOCENTE, el payload puede incluir:
|   - materias_habilitadas: array de materia_id (checkboxes MAT/FIS/ING/COMP)
|   - descripcion_estructurada: JSON con { profesion, experiencias[], formacion_adicional }
|
| El campo BD 'descripcion' guarda el JSON serializado para docentes. Para
| otros roles se ignora (queda null).
*/
class UsuarioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = User::with(['rol', 'materiasHabilitadas:id,codigo,nombre']);

        if ($busqueda = $request->query('q')) {
            $q->where(function ($qq) use ($busqueda) {
                $qq->where('nombre', 'ilike', "%{$busqueda}%")
                   ->orWhere('apellidos', 'ilike', "%{$busqueda}%")
                   ->orWhere('email', 'ilike', "%{$busqueda}%");
            });
        }

        $usuarios = $q->orderBy('id', 'desc')->paginate(20);

        // Contadores de asignaciones activas para docentes (gestion activa)
        $gestionActiva = \App\Models\GestionCup::whereIn('estado', ['ACTIVA', 'BORRADOR'])
            ->orderByDesc('id')
            ->value('id');
        $contadores = $gestionActiva
            ? AsignacionDocenteService::contadoresPorDocente((int) $gestionActiva)
            : [];

        $usuarios->getCollection()->transform(function (User $u) use ($contadores) {
            $arr = $u->toArray();
            $arr['perfil_docente'] = $this->parsearDescripcion($u->descripcion);
            $arr['asignaciones_usadas'] = (int) ($contadores[$u->id] ?? 0);
            $arr['asignaciones_maximo'] = (int) config('cup.MAX_ASIGNACIONES_DOCENTE', 4);
            return $arr;
        });

        return response()->json($usuarios);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role_id'          => ['required', 'exists:roles,id'],
            'nombre'           => ['required', 'string', 'max:100'],
            'apellidos'        => ['required', 'string', 'max:100'],
            'fecha_nacimiento' => ['nullable', 'date'],
            'ci'               => ['nullable', 'string', 'max:20'],
            'telefono'         => ['nullable', 'string', 'max:20'],
            'email'            => ['required', 'email', 'unique:users,email'],
            'password'         => ['required', PasswordRule::min(8)->mixedCase()->numbers()],
            'activo'           => ['boolean'],
        ]);

        $esDocente = $this->esRolDocente((int) $data['role_id']);
        $extras    = $esDocente ? $this->validarExtrasDocente($request) : ['materias' => [], 'descripcion' => null];

        $data['password']    = Hash::make($data['password']);
        $data['descripcion'] = $extras['descripcion'];

        $user = DB::transaction(function () use ($data, $extras, $esDocente) {
            $u = User::create($data);
            if ($esDocente) {
                $u->materiasHabilitadas()->sync($extras['materias']);
            }
            return $u;
        });

        BitacoraService::registrar('USUARIO_CREADO', 'user', $user->id, [
            'rol'                => $user->rol?->codigo,
            'materias_habilitadas' => $extras['materias'],
        ]);

        return response()->json($this->cargarRelacionesUsuario($user), 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($this->cargarRelacionesUsuario($user));
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'role_id'          => ['sometimes', 'exists:roles,id'],
            'nombre'           => ['sometimes', 'string', 'max:100'],
            'apellidos'        => ['sometimes', 'string', 'max:100'],
            'fecha_nacimiento' => ['sometimes', 'nullable', 'date'],
            'ci'               => ['sometimes', 'nullable', 'string', 'max:20'],
            'telefono'         => ['sometimes', 'nullable', 'string', 'max:20'],
            'email'            => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password'         => ['sometimes', 'nullable', PasswordRule::min(8)->mixedCase()->numbers()],
            'activo'           => ['sometimes', 'boolean'],
        ]);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        // Rol efectivo tras el update (si viene en payload, ese; si no, el actual)
        $rolIdEfectivo = (int) ($data['role_id'] ?? $user->role_id);
        $esDocente     = $this->esRolDocente($rolIdEfectivo);

        $extras = $esDocente
            ? $this->validarExtrasDocente($request, opcional: true)
            : ['materias' => null, 'descripcion' => null];

        DB::transaction(function () use ($user, $data, $extras, $esDocente) {
            // Descripcion solo se pisa si el request la trajo explicitamente
            if ($esDocente && $extras['descripcion'] !== null) {
                $data['descripcion'] = $extras['descripcion'];
            } elseif (!$esDocente) {
                // Al cambiar de rol a NO-docente, se limpian estructuras del perfil docente
                $data['descripcion'] = null;
                $user->materiasHabilitadas()->sync([]);
            }

            $user->update($data);

            if ($esDocente && is_array($extras['materias'])) {
                $user->materiasHabilitadas()->sync($extras['materias']);
            }
        });

        BitacoraService::registrar('USUARIO_MODIFICADO', 'user', $user->id, [
            'rol' => $user->fresh()->rol?->codigo,
        ]);

        return response()->json($this->cargarRelacionesUsuario($user->fresh()));
    }

    public function toggleActivo(User $user): JsonResponse
    {
        $user->activo = !$user->activo;
        $user->save();
        BitacoraService::registrar($user->activo ? 'USUARIO_ACTIVADO' : 'USUARIO_INACTIVADO', 'user', $user->id);

        return response()->json(['user' => $this->cargarRelacionesUsuario($user)]);
    }

    // ------------------------------------------------------------------
    // Helpers privados
    // ------------------------------------------------------------------

    private function esRolDocente(int $roleId): bool
    {
        return Rol::whereKey($roleId)->value('codigo') === 'DOCENTE';
    }

    /**
     * Valida y normaliza los extras del perfil docente (materias + descripcion
     * estructurada). Cuando $opcional=true, los campos pueden venir ausentes.
     *
     * Retorna:
     *   [ 'materias' => int[]|null, 'descripcion' => string|null (JSON) ]
     */
    private function validarExtrasDocente(Request $request, bool $opcional = false): array
    {
        $minExp = (int) config('cup.DOCENTE_DESCRIPCION_MIN_EXPERIENCIAS', 2);
        $minProf = (int) config('cup.DOCENTE_PROFESION_MIN_LENGTH', 5);
        $minExpLen = (int) config('cup.DOCENTE_EXPERIENCIA_MIN_LENGTH', 15);

        $reglas = [
            'materias_habilitadas'    => [$opcional ? 'sometimes' : 'required', 'array', 'min:1'],
            'materias_habilitadas.*'  => ['integer', 'exists:materias,id'],

            'descripcion_estructurada'                       => [$opcional ? 'sometimes' : 'required', 'array'],
            'descripcion_estructurada.profesion'             => ['required_with:descripcion_estructurada', 'string', "min:{$minProf}", 'max:120'],
            'descripcion_estructurada.experiencias'          => ['required_with:descripcion_estructurada', 'array', "min:{$minExp}"],
            'descripcion_estructurada.experiencias.*'        => ['string', "min:{$minExpLen}", 'max:200'],
            'descripcion_estructurada.formacion_adicional'   => ['nullable', 'string', 'max:400'],
        ];

        $mensajes = [
            'materias_habilitadas.required' => 'Debe seleccionar al menos una materia habilitada para el docente.',
            'materias_habilitadas.min'      => 'Debe seleccionar al menos una materia.',
            'descripcion_estructurada.experiencias.min' => "Debe registrar al menos {$minExp} experiencias.",
            'descripcion_estructurada.profesion.min'    => "La profesion debe tener al menos {$minProf} caracteres.",
            'descripcion_estructurada.experiencias.*.min' => "Cada experiencia debe tener al menos {$minExpLen} caracteres.",
        ];

        $validated = $request->validate($reglas, $mensajes);

        $materias    = $validated['materias_habilitadas'] ?? null;
        $descripcion = null;
        if (!empty($validated['descripcion_estructurada'])) {
            $descripcion = json_encode([
                'profesion'           => trim($validated['descripcion_estructurada']['profesion']),
                'experiencias'        => array_values(array_map('trim', $validated['descripcion_estructurada']['experiencias'])),
                'formacion_adicional' => isset($validated['descripcion_estructurada']['formacion_adicional'])
                    ? trim($validated['descripcion_estructurada']['formacion_adicional'])
                    : null,
            ], JSON_UNESCAPED_UNICODE);
        }

        return [
            'materias'    => $materias,
            'descripcion' => $descripcion,
        ];
    }

    /**
     * Decodifica el campo 'descripcion' como JSON estructurado. Si es texto
     * plano legado, se envuelve en { profesion: null, experiencias: [texto] }
     * para no romper la UI historica.
     */
    private function parsearDescripcion(?string $raw): ?array
    {
        if (!$raw) {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && array_key_exists('profesion', $decoded)) {
            return [
                'profesion'           => $decoded['profesion'] ?? null,
                'experiencias'        => $decoded['experiencias'] ?? [],
                'formacion_adicional' => $decoded['formacion_adicional'] ?? null,
            ];
        }
        // Compatibilidad hacia atras: descripcion legada (texto plano).
        return [
            'profesion'           => null,
            'experiencias'        => [$raw],
            'formacion_adicional' => null,
        ];
    }

    private function cargarRelacionesUsuario(User $user): array
    {
        $user->load(['rol', 'materiasHabilitadas:id,codigo,nombre']);
        $gestionActiva = \App\Models\GestionCup::whereIn('estado', ['ACTIVA', 'BORRADOR'])
            ->orderByDesc('id')
            ->value('id');
        $asignadas = $gestionActiva
            ? AsignacionDocenteService::contarAsignacionesActivas($user->id, (int) $gestionActiva)
            : 0;

        $arr = $user->toArray();
        $arr['perfil_docente']      = $this->parsearDescripcion($user->descripcion);
        $arr['asignaciones_usadas'] = $asignadas;
        $arr['asignaciones_maximo'] = (int) config('cup.MAX_ASIGNACIONES_DOCENTE', 4);
        return $arr;
    }
}
