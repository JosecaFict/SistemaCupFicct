<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BitacoraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/*
| PerfilController
| --------------------------------------------------------------------------
| Autogestion de la cuenta del usuario autenticado (cualquier rol):
|   PUT /api/auth/perfil    -> actualiza sus datos personales
|   PUT /api/auth/password  -> cambia su contrasena (pide la actual)
*/
class PerfilController extends Controller
{
    /** Actualiza nombre, apellidos y email del usuario autenticado. */
    public function actualizarDatos(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'nombre'    => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'email'     => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($data);

        BitacoraService::registrar('PERFIL_ACTUALIZADO', 'user', $user->id);

        return response()->json(['user' => $user->fresh()->load('rol')]);
    }

    /** Cambia la contrasena del usuario autenticado, validando la actual. */
    public function cambiarPassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            // Misma politica que el reset: 8+, mayuscula, minuscula y numero.
            'password'         => ['required', 'confirmed', PasswordRule::min(8)->mixedCase()->numbers()],
        ]);

        if (!Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'La contrasena actual no es correcta.',
            ]);
        }

        $user->password = Hash::make($data['password']);
        $user->setRememberToken(Str::random(60));
        $user->save();

        BitacoraService::registrar('PASSWORD_CAMBIADO_PERFIL', 'user', $user->id);

        return response()->json(['message' => 'Contrasena actualizada correctamente.']);
    }
}
