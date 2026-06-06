<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

/*
| UsuarioController (CU2)
| --------------------------------------------------------------------------
| CRUD de usuarios con login. Solo ADMINISTRADOR.
| Activacion/inactivacion via PATCH /toggle-activo.
*/
class UsuarioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = User::with('rol');

        if ($busqueda = $request->query('q')) {
            $q->where(function ($qq) use ($busqueda) {
                $qq->where('nombre', 'ilike', "%{$busqueda}%")
                   ->orWhere('apellidos', 'ilike', "%{$busqueda}%")
                   ->orWhere('email', 'ilike', "%{$busqueda}%");
            });
        }

        return response()->json($q->orderBy('id', 'desc')->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role_id'   => ['required', 'exists:roles,id'],
            'nombre'    => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'email'     => ['required', 'email', 'unique:users,email'],
            'password'  => ['required', PasswordRule::min(8)->mixedCase()->numbers()],
            'activo'    => ['boolean'],
        ]);

        $data['password'] = Hash::make($data['password']);
        $user = User::create($data);

        return response()->json($user->load('rol'), 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($user->load('rol'));
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'role_id'   => ['sometimes', 'exists:roles,id'],
            'nombre'    => ['sometimes', 'string', 'max:100'],
            'apellidos' => ['sometimes', 'string', 'max:100'],
            'email'     => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password'  => ['sometimes', 'nullable', PasswordRule::min(8)->mixedCase()->numbers()],
            'activo'    => ['sometimes', 'boolean'],
        ]);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return response()->json($user->fresh()->load('rol'));
    }

    public function toggleActivo(User $user): JsonResponse
    {
        $user->activo = !$user->activo;
        $user->save();

        return response()->json(['user' => $user->load('rol')]);
    }
}
