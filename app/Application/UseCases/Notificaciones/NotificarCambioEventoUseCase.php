<?php

namespace App\Application\UseCases\Notificaciones;

use App\Domain\Abstractions\Notificaciones\INotificacionRepository;
use App\Domain\Entities\Notificaciones\Notificacion;
use App\Domain\Enums\Notificaciones\CanalNotificacion;
use App\Domain\Enums\Notificaciones\EstadoNotificacion;
use App\Domain\Enums\Pagos\EstadoPago;
use App\Domain\Enums\Recintos\EstadoEvento;
use App\Domain\Enums\Reservas\EstadoReserva;
use App\Jobs\Notificaciones\EnviarNotificacionCorreoJob;
use Carbon\Carbon;
use DomainException;
use Illuminate\Support\Facades\DB;

class NotificarCambioEventoUseCase
{
    public function __construct(
        private readonly INotificacionRepository $notificaciones
    ) {}

    public function notificarCancelacion(int $eventoId): int
    {
        $evento = $this->obtenerEvento($eventoId);

        if ($evento->estado !== EstadoEvento::CANCELADO->value) {
            throw new DomainException(
                'El evento todavía no está cancelado.'
            );
        }

        return $this->notificarCompradores(
            eventoId: $eventoId,
            asunto: 'Evento cancelado: ' . $evento->nombre,
            mensaje: 'Lamentamos informarte que la función de '
                . $evento->nombre
                . ' fue cancelada. '
                . 'Te devolvemos el 100 % de tu compra automáticamente: '
                . 'recibirás otro aviso cuando se haga la devolución.',
            claveBase: 'evento_cancelado:' . $eventoId,
            tipo: 'evento_cancelado'
        );
    }

    public function notificarCambioFecha(
        int $eventoId,
        string $fechaAnterior,
        // Las fechas se guardan en UTC; el correo las muestra en hora local
        string $zonaHoraria = 'America/Guatemala'
    ): int {
        $evento = $this->obtenerEvento($eventoId);

        if (!in_array($evento->estado, [
            EstadoEvento::PUBLICADO->value,
            EstadoEvento::AGOTADO->value,
        ], true)) {
            throw new DomainException(
                'El estado del evento no permite este aviso.'
            );
        }

        try {
            $anterior = Carbon::parse($fechaAnterior);
            $nueva = Carbon::parse($evento->fecha_evento);
        } catch (\Throwable $e) {
            throw new DomainException(
                'La fecha anterior no es válida.',
                previous: $e
            );
        }

        if ($anterior->equalTo($nueva)) {
            throw new DomainException(
                'La fecha del evento no ha cambiado.'
            );
        }

        $texto = fn (Carbon $fecha) => $fecha->copy()
            ->setTimezone($zonaHoraria)
            ->locale('es')
            ->isoFormat('dddd D [de] MMMM [de] YYYY, h:mm a');

        return $this->notificarCompradores(
            eventoId: $eventoId,
            asunto: 'Cambio de fecha: ' . $evento->nombre,
            mensaje: 'La función de ' . $evento->nombre . ' cambió de fecha.'
                . "\n\nAntes: " . $texto($anterior)
                . "\nAhora: " . $texto($nueva)
                . "\n\nTus boletos siguen siendo válidos para la nueva fecha: no necesitas hacer nada. "
                . 'Si la nueva fecha no te funciona, puedes pedir el reembolso del 100 % '
                . 'desde Mis compras, en cualquier momento antes de que empiece la función.',
            claveBase: 'evento_fecha:' . $eventoId
                . ':' . $nueva->format('YmdHis'),
            tipo: 'cambio_fecha'
        );
    }

    private function obtenerEvento(int $eventoId): object
    {
        if ($eventoId <= 0) {
            throw new DomainException(
                'El identificador del evento no es válido.'
            );
        }

        $evento = DB::table('eventos')
            ->where('id', $eventoId)
            ->whereNull('deleted_at')
            ->first();

        if ($evento === null) {
            throw new DomainException(
                'El evento no existe.'
            );
        }

        return $evento;
    }

    private function notificarCompradores(
        int $eventoId,
        string $asunto,
        string $mensaje,
        string $claveBase,
        ?string $tipo = null
    ): int {
        $programados = 0;

        DB::table('reservas as r')
            ->join('pagos as p', 'p.reserva_id', '=', 'r.id')
            ->where('r.evento_id', $eventoId)
            ->where('r.estado', EstadoReserva::CONFIRMADA->value)
            ->where('p.estado', EstadoPago::APROBADO->value)
            ->whereNull('r.deleted_at')
            ->whereNull('p.deleted_at')
            ->select('r.usuario_id')
            ->distinct()
            ->orderBy('r.usuario_id')
            ->chunk(200, function ($compradores) use (
                $asunto,
                $mensaje,
                $claveBase,
                $tipo,
                &$programados
            ) {
                foreach ($compradores as $comprador) {
                    $usuarioId = (int) $comprador->usuario_id;

                    $notificacion = new Notificacion(
                        id: null,
                        usuarioId: $usuarioId,
                        canal: CanalNotificacion::EMAIL,
                        asunto: $asunto,
                        mensaje: $mensaje,
                        estado: EstadoNotificacion::PENDIENTE,
                        tipo: $tipo,
                        url: '/mis-compras'
                    );

                    $guardada = $this->notificaciones->guardarUnica(
                        notificacion: $notificacion,
                        claveIdempotencia: $claveBase
                            . ':usuario:' . $usuarioId
                    );

                    if (
                        $guardada->getEstado()
                        === EstadoNotificacion::PENDIENTE
                    ) {
                        EnviarNotificacionCorreoJob::dispatch(
                            $guardada->getId()
                        )->afterCommit();

                        $programados++;
                    }
                }
            });

        return $programados;
    }
}
