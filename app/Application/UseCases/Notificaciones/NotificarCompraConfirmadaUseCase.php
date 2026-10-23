<?php

namespace App\Application\UseCases\Notificaciones;

use App\Domain\Abstractions\Notificaciones\INotificacionRepository;
use App\Domain\Entities\Notificaciones\Notificacion;
use App\Domain\Enums\Boletos\EstadoBoleto;
use App\Domain\Enums\Notificaciones\CanalNotificacion;
use App\Domain\Enums\Notificaciones\EstadoNotificacion;
use App\Domain\Enums\Pagos\EstadoPago;
use App\Domain\Enums\Reservas\EstadoReserva;
use App\Jobs\Notificaciones\EnviarNotificacionCorreoJob;
use DomainException;
use Illuminate\Support\Facades\DB;

class NotificarCompraConfirmadaUseCase
{
    public function __construct(
        private readonly INotificacionRepository $notificaciones
    ) {}

    public function execute(int $pagoId): Notificacion
    {
        if ($pagoId <= 0) {
            throw new DomainException(
                'El identificador del pago no es válido.'
            );
        }

        // 1. Ejecutar las validaciones dentro de una transaccion.
        $notificacion = DB::transaction(function () use ($pagoId) {

            // 2. Comprobar que el pago este aprobado.
            $pago = DB::table('pagos')
                ->where('id', $pagoId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if (
                $pago === null ||
                $pago->estado !== EstadoPago::APROBADO->value
            ) {
                throw new DomainException(
                    'El pago no existe o no está aprobado.'
                );
            }

            // 3. Comprobar que la reserva este confirmada.
            $reserva = DB::table('reservas')
                ->where('id', $pago->reserva_id)
                ->whereNull('deleted_at')
                ->first();

            if (
                $reserva === null ||
                $reserva->estado !== EstadoReserva::CONFIRMADA->value
            ) {
                throw new DomainException(
                    'La reserva no está confirmada.'
                );
            }

            // 4. Comprobar que existan boletos digitales.
            $tieneBoletos = DB::table('boletos')
                ->where('reserva_id', $reserva->id)
                ->whereNull('deleted_at')
                ->whereIn('estado', [
                    EstadoBoleto::EMITIDO->value,
                    EstadoBoleto::USADO->value,
                ])
                ->exists();

            if (!$tieneBoletos) {
                throw new DomainException(
                    'Todavía no existen boletos emitidos para esta compra.'
                );
            }

            // 5. Preparar la notificacion.
            $nuevaNotificacion = new Notificacion(
                id: null,
                usuarioId: (int) $reserva->usuario_id,
                canal: CanalNotificacion::EMAIL,
                asunto: 'Confirmación de compra',
                mensaje: 'Tu compra fue confirmada correctamente. '
                    . 'Tus boletos digitales ya están disponibles '
                    . 'para consultar y presentar en el evento.',
                estado: EstadoNotificacion::PENDIENTE,
                tipo: 'compra_confirmada',
                url: '/mis-compras/' . $reserva->id . '/boletos'
            );

            // 6. Crear una clave unica para este pago.
            $clave = 'compra_confirmada:pago:' . $pagoId;

            // 7. Guardar la notificacion sin duplicarla.
            return $this->notificaciones->guardarUnica(
                notificacion: $nuevaNotificacion,
                claveIdempotencia: $clave
            );

        }, 3);

        // 8. Programar el envio si sigue pendiente.
        if (
            $notificacion->getEstado()
            === EstadoNotificacion::PENDIENTE
        ) {
            EnviarNotificacionCorreoJob::dispatch(
                $notificacion->getId()
            )->afterCommit();
        }

        // 9. Devolver la notificacion registrada.
        return $notificacion;
    }
}
