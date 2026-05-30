<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BitacoraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/*
| AuthController
| --------------------------------------------------------------------------
| Gestiona el flujo de autenticacion del SPA via Laravel Sanctum (cookies de
| sesion). Endpoints:
|   POST /api/auth/login              -> inicia sesion
|   POST /api/auth/logout             -> cierra sesion (requiere auth)
|   GET  /api/auth/me                 -> devuelve el usuario autenticado
|   POST /api/auth/forgot-password    -> envia correo con token de reset
|   POST /api/auth/reset-password     -> aplica el reset usando el token
*/
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credenciales = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Verificar credenciales y que el usuario este activo
        $user = User::where('email', $credenciales['email'])->first();
        if (!$user || !Hash::check($credenciales['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('Las credenciales no coinciden con nuestros registros.'),
            ]);
        }
        if (!$user->activo) {
            throw ValidationException::withMessages([
                'email' => __('Usuario inactivo. Contacte al administrador.'),
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        $user->last_login_at = now();
        $user->save();

        BitacoraService::registrar('LOGIN', 'user', $user->id);

        return response()->json([
            'user' => $user->load('rol'),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $userId = Auth::id();
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($userId) {
            BitacoraService::registrar('LOGOUT', 'user', $userId);
        }

        return response()->json(['message' => 'Sesion cerrada.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()->load('rol')]);
    }

    /** Envia el correo con el link de recuperacion. */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        return response()->json([
            'status' => $status,
            'message' => $status === Password::RESET_LINK_SENT
                ? 'Se envio un correo con instrucciones para reestablecer la contrasena.'
                : 'No fue posible enviar el correo de recuperacion.',
        ], $status === Password::RESET_LINK_SENT ? 200 : 422);
    }

    /** Aplica el reset usando el token recibido en el correo. */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token'                 => ['required'],
            'email'                 => ['required', 'email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->password = Hash::make($password);
                $user->setRememberToken(\Illuminate\Support\Str::random(60));
                $user->save();
            }
        );

        return response()->json([
            'status' => $status,
            'message' => $status === Password::PASSWORD_RESET
                ? 'Contrasena reestablecida correctamente.'
                : 'No fue posible reestablecer la contrasena.',
        ], $status === Password::PASSWORD_RESET ? 200 : 422);
    }
}
