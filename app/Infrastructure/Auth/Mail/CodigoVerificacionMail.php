<?php
namespace App\Infrastructure\Auth\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// ShouldQueue: Mail::send() NO lo envía al momento; lo deja en la cola (Redis)
// y el worker del contenedor "queue" lo envía por SMTP en segundo plano.
class CodigoVerificacionMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $nombre,
        public readonly string $codigo,
        public readonly int $minutosValidez,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Tu código de verificación');
    }

    public function content(): Content
    {
        return new Content(view: 'correos.auth.codigo-verificacion');
    }
}
