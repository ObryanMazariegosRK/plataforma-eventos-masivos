<?php

namespace App\Application\UseCases\Notificaciones;

use App\Domain\Abstractions\Notificaciones\INotificacionRepository;
use App\Domain\Entities\Notificaciones\Notificacion;
use App\Domain\Enums\Boletos\EstadoBoleto;
use App\Domain\Enums\Notificaciones\CanalNotificacion;
use App\Domain\Enums\Notificaciones\EstadoNotificacion;
use App\Domain\Enums\Reservas\EstadoReserva;
use App\Jobs\Notificaciones\EnviarNotificacionCorreoJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProgramarRecordatoriosEventosUseCase
{
    public function __construct(
        private readonly INotificacionRepository $notificaciones
    ) {}

    public function execute(): int
    {
        // Buscar eventos que ocurriran en 24 a 25 horas.
        $desde = now()->addHours(24);
        $hasta = now()->addHours(25);

        $programados = 0;

        // Obtener compradores con boletos emitidos.
        // DISTINCT evita repetir compradores con varios boletos.
        DB::table('eventos as e')
            ->join('reservas as r', 'r.evento_id', '=', 'e.id')
            ->join('boletos as b', 'b.reserva_id', '=', 'r.id')
            ->select([
                'e.id as evento_id',
                'e.nombre as evento_nombre',
                'e.fecha_evento',
                'r.usuario_id',
            ])
            ->distinct()
            ->where('e.estado', 'publicado')
            ->where('r.estado', EstadoReserva::CONFIRMADA->value)
            ->where('b.estado', EstadoBoleto::EMITIDO->value)
            ->whereNull('e.deleted_at')
            ->whereNull('r.deleted_at')
            ->whereNull('b.deleted_at')
            ->whereBetween('e.fecha_evento', [$desde, $hasta])
            ->orderBy('e.id')
            ->orderBy('r.usuario_id')
            ->chunk(200, function ($compras) use (&$programados) {

                foreach ($compras as $compra) {

                    // Se guarda en UTC; el aviso la dice en hora de Guatemala
                    $fecha = Carbon::parse($compra->fecha_evento)
                        ->setTimezone('America/Guatemala')
                        ->locale('es')
                        ->isoFormat('dddd D [de] MMMM, h:mm a');

                    $notificacion = new Notificacion(
                        id: null,
                        usuarioId: (int) $compra->usuario_id,
                        canal: CanalNotificacion::EMAIL,
                        asunto: 'Recordatorio de evento: '
                            . $compra->evento_nombre,
                        mensaje: 'Te recordamos que el evento '
                            . $compra->evento_nombre
                            . ' se realizará el ' . $fecha
                            // La hora ya termina en "a. m." / "p. m."
                            . ' Recuerda llevar tu boleto digital.',
                        estado: EstadoNotificacion::PENDIENTE,
                        tipo: 'recordatorio',
                        url: '/mis-compras'
                    );

                    // Un recordatorio por usuario y evento.
                    $clave = 'recordatorio_24h:evento:'
                        . $compra->evento_id
                        . ':usuario:' . $compra->usuario_id;

                    $guardada = $this->notificaciones->guardarUnica(
                        notificacion: $notificacion,
                        claveIdempotencia: $clave
                    );

                    // No reenviar notificaciones ya enviadas.
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
