<?php

namespace App\Infrastructure\Notificaciones\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NotificacionCorreo extends Mailable
{
    public function __construct(
        public readonly string $asunto,
        public readonly string $mensaje
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->asunto
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notificaciones.mensaje',
            with: [
                'mensaje' => $this->mensaje,
            ]
        );
    }
}
