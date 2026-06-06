<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BitacoraService;
use App\Services\CorreoService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

/*
| AuthController
| --------------------------------------------------------------------------
| Gestiona el flujo de autenticacion del SPA via Laravel Sanctum (cookies de
| sesion). Endpoints:
|   POST /api/auth/login              -> inicia sesion
|   POST /api/auth/logout             -> cierra sesion (requiere auth)
|   GET  /api/auth/me                 -> devuelve el usuario autenticado
|   POST /api/auth/forgot-password    -> envia un codigo OTP al correo
|   POST /api/auth/reset-password     -> valida el codigo OTP y cambia la clave
*/
class AuthController extends Controller
{
    /** Minutos de validez del codigo OTP de recuperacion. */
    private const OTP_MINUTOS = 15;

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

    /**
     * Genera un codigo OTP de 6 digitos y lo envia al correo registrado.
     * El codigo se guarda hasheado en password_reset_tokens con su fecha,
     * para validarlo y expirarlo luego. Responde siempre de forma generica
     * para no revelar si el correo existe (evita enumeracion de usuarios).
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $request->email)->first();

        if ($user && $user->activo) {
            // Codigo de 6 digitos (random_int es criptograficamente seguro).
            $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($codigo), 'created_at' => now()]
            );

            CorreoService::enviarCodigoRecuperacion($user->email, $codigo, self::OTP_MINUTOS);

            BitacoraService::registrar('PASSWORD_OTP_SOLICITADO', 'user', $user->id);
        }

        return response()->json([
            'message' => 'Si el correo esta registrado, te enviamos un codigo de recuperacion.',
        ]);
    }

    /**
     * Valida el codigo OTP y, si es correcto y no expiro, cambia la contrasena.
     * El codigo es de un solo uso: se elimina al usarse (o al expirar).
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'codigo'   => ['required', 'string', 'size:6'],
            // Politica: 8+ caracteres, mayuscula, minuscula y numero.
            'password' => [
                'required', 'confirmed',
                PasswordRule::min(8)->mixedCase()->numbers(),
            ],
        ]);

        $registro = DB::table('password_reset_tokens')->where('email', $data['email'])->first();

        // Mensaje generico para codigo inexistente o que no coincide.
        if (!$registro || !Hash::check($data['codigo'], $registro->token)) {
            return response()->json(['message' => 'Codigo invalido o ya utilizado.'], 422);
        }

        // Expiracion del codigo.
        if (Carbon::parse($registro->created_at)->addMinutes(self::OTP_MINUTOS)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $data['email'])->delete();
            return response()->json(['message' => 'El codigo expiro. Solicita uno nuevo.'], 422);
        }

        $user = User::where('email', $data['email'])->first();
        if (!$user) {
            return response()->json(['message' => 'Codigo invalido o ya utilizado.'], 422);
        }

        $user->password = Hash::make($data['password']);
        $user->setRememberToken(Str::random(60));
        $user->save();

        // Un solo uso: el codigo se invalida tras cambiar la contrasena.
        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

        BitacoraService::registrar('PASSWORD_REESTABLECIDO', 'user', $user->id);

        return response()->json(['message' => 'Contrasena reestablecida correctamente.']);
    }
}
