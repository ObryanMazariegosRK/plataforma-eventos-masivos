<?php

namespace App\Jobs\Notificaciones;

use App\Domain\Abstractions\Notificaciones\INotificacionRepository;
use App\Domain\Enums\Notificaciones\CanalNotificacion;
use App\Domain\Enums\Notificaciones\EstadoNotificacion;
use App\Infrastructure\Notificaciones\Mail\NotificacionCorreo;
use DateTimeImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class EnviarNotificacionCorreoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    
    public function __construct(
        public readonly int $notificacionId
    ) {
        // Usar la cola que ya procesa Docker.
        $this->onQueue('default');
    }


    public function handle(
        INotificacionRepository $repositorio
    ): void {

        // Evitar envios simultaneos de la misma notificacion.
        $bloqueo = Cache::lock(
            'envio_notificacion:' . $this->notificacionId,
            120
        );

        if (!$bloqueo->get()) {
            throw new RuntimeException(
                'La notificacion esta siendo procesada.'
            );
        }

        try {

            // 1. Recuperar la notificacion.
            $notificacion = $repositorio->buscarPorId(
                $this->notificacionId
            );

            if ($notificacion === null) {
                return;
            }

            // 2. No volver a enviar una notificacion enviada.
            if (
                $notificacion->getEstado()
                === EstadoNotificacion::ENVIADA
            ) {
                return;
            }

            // 3. Solo procesar correos.
            if (
                $notificacion->getCanal()
                !== CanalNotificacion::EMAIL
            ) {
                return;
            }

            try {

                // 4. Buscar el destinatario.
                $correo = DB::table('usuarios')
                    ->where('id', $notificacion->getUsuarioId())
                    ->value('email');

                if (
                    !is_string($correo) ||
                    !filter_var($correo, FILTER_VALIDATE_EMAIL)
                ) {
                    throw new RuntimeException(
                        'El destinatario no tiene un correo valido.'
                    );
                }

                // 5. Enviar el mensaje por SMTP.
                Mail::to($correo)->send(
                    new NotificacionCorreo(
                        asunto: $notificacion->getAsunto(),
                        mensaje: $notificacion->getMensaje()
                    )
                );

                // 6. SMTP acepto el mensaje.
                $notificacion->marcarEnviada(
                    new DateTimeImmutable()
                );

                $repositorio->guardar($notificacion);

            } catch (Throwable $e) {

                // Registrar el error para su revision.
                $notificacion->marcarFallida();

                $repositorio->guardar($notificacion);

                throw $e;
            }

        } finally {

            // Liberar siempre el bloqueo.
            $bloqueo->release();
        }
    }
}
