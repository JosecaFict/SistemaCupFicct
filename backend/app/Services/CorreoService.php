<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;

/*
| CorreoService
| --------------------------------------------------------------------------
| Envia correos a traves de la API HTTP de Brevo (https://api.brevo.com).
|
| Por que API HTTP y no SMTP:
|   Railway bloquea los puertos SMTP (25/465/587) de salida, asi que el
|   transporte SMTP de Laravel se cuelga hasta el timeout. La API de Brevo
|   viaja por HTTPS (443), que no esta bloqueado, y usa el cliente HTTP que
|   Laravel ya trae (sin paquetes extra).
|
| Requiere la variable de entorno BREVO_API_KEY y un remitente verificado
| en Brevo (MAIL_FROM_ADDRESS).
*/
class CorreoService
{
    private const BREVO_ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    /** Envia el codigo OTP de recuperacion al correo indicado. */
    public static function enviarCodigoRecuperacion(string $email, string $codigo, int $minutos = 15): void
    {
        $html = View::make('emails.codigo-recuperacion', [
            'codigo'  => $codigo,
            'minutos' => $minutos,
        ])->render();

        self::enviar(
            $email,
            'Codigo de recuperacion - Sistema CUP FICCT',
            $html
        );
    }

    /** Envia un correo HTML generico via Brevo. Lanza excepcion si falla. */
    private static function enviar(string $destino, string $asunto, string $html): void
    {
        $respuesta = Http::withHeaders([
            'api-key'      => config('services.brevo.key'),
            'accept'       => 'application/json',
            'content-type' => 'application/json',
        ])->post(self::BREVO_ENDPOINT, [
            'sender'      => [
                'name'  => config('mail.from.name', 'Sistema CUP FICCT'),
                'email' => config('mail.from.address'),
            ],
            'to'          => [['email' => $destino]],
            'subject'     => $asunto,
            'htmlContent' => $html,
        ]);

        // 4xx/5xx -> excepcion (clave invalida, remitente no verificado, etc.)
        $respuesta->throw();
    }
}
