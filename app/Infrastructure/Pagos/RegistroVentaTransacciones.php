<?php
namespace App\Infrastructure\Pagos;

use App\Application\UseCases\Transacciones\RegistrarOperacionHistorialUseCase;
use App\Application\UseCases\Transacciones\RegistrarTransaccionUseCase;
use App\Domain\Abstractions\Pagos\IRegistroVenta;
use App\Domain\Entities\Pagos\Pago;
use App\Domain\Entities\Reservas\Reserva;
use App\Domain\Enums\Transacciones\TipoOperacionHistorial;
use App\Domain\Enums\Transacciones\TipoTransaccion;

/**
 * Adaptador hacia el módulo Transacciones (Freyder): Pagos no conoce sus clases,
 * solo la interfaz IRegistroVenta. Si Transacciones cambia, solo se toca este archivo.
 */
class RegistroVentaTransacciones implements IRegistroVenta
{
    public function __construct(
        private RegistrarTransaccionUseCase $registrarTransaccion,
        private RegistrarOperacionHistorialUseCase $registrarHistorial,
    ) {}

    public function registrar(Pago $pago, Reserva $reserva, string $codigoPasarela): void
    {
        $this->registrarTransaccion->execute(
            tipo: TipoTransaccion::VENTA,
            monto: $pago->getMonto(),
            usuarioId: $reserva->getUsuarioId(),
            reservaId: $reserva->getId(),
            pagoId: $pago->getId(),
            referencia: 'pago-'.$pago->getId(),   // único por tipo: evita registrar la venta dos veces
            descripcion: "Pago aprobado por la pasarela {$codigoPasarela}",
            registradoPor: $reserva->getUsuarioId(),
        );

        $this->registrarHistorial->execute(
            tipo: TipoOperacionHistorial::VENTA_CONFIRMADA,
            claveIdempotencia: 'venta-confirmada-pago-'.$pago->getId(),
            usuarioId: $reserva->getUsuarioId(),
            eventoId: $reserva->getEventoId(),
            reservaId: $reserva->getId(),
            pagoId: $pago->getId(),
            registradoPor: $reserva->getUsuarioId(),
            descripcion: 'Venta confirmada por pago aprobado',
            datos: [
                'monto'      => $pago->getMonto(),
                'pasarela'   => $codigoPasarela,
                'referencia' => $pago->getReferenciaExterna(),
            ],
        );
    }
}
