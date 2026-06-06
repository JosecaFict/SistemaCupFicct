<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/*
| CodigoRecuperacion
| --------------------------------------------------------------------------
| Correo con el codigo OTP de 6 digitos para recuperar la contrasena.
| Las propiedades publicas ($codigo, $minutos) quedan disponibles en la vista.
*/
class CodigoRecuperacion extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $codigo, public int $minutos = 15)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Codigo de recuperacion - Sistema CUP FICCT',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.codigo-recuperacion',
        );
    }
}
