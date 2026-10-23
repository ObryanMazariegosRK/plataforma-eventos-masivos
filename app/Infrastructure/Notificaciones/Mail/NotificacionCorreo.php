<?php

namespace App\Infrastructure\Notificaciones\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class NotificacionCorreo extends Mailable
{
    public function __construct(
        public readonly string $asunto,
        public readonly string $mensaje,
        // Dirección completa de lo que el aviso pide revisar (boletos, Reembolsos del panel…)
        public readonly ?string $enlace = null,
        public readonly ?string $nombre = null,
        public readonly ?string $textoBoton = null
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
                'enlace' => $this->enlace,
                'nombre' => $this->nombre,
                'textoBoton' => $this->textoBoton,
                'asunto' => $this->asunto,
            ]
        );
    }
}
