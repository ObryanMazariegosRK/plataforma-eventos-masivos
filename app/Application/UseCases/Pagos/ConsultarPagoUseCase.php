<?php
namespace App\Application\UseCases\Pagos;

use App\Application\Abstractions\Pagos\IConsultarPagoUseCase;
use App\Domain\Abstractions\Pagos\IPagoRepository;
use App\Domain\Abstractions\Reservas\IReservaRepository;
use App\Domain\Entities\Pagos\Pago;
use App\Domain\Exceptions\Pagos\PagoNoEncontradoException;

class ConsultarPagoUseCase implements IConsultarPagoUseCase
{
    public function __construct(
        private IPagoRepository $pagos,
        private IReservaRepository $reservas,
    ) {}

    public function execute(int $usuarioId, int $pagoId): Pago
    {
        $pago = $this->pagos->buscarPorId($pagoId);
        $reserva = $pago ? $this->reservas->buscarPorId($pago->getReservaId()) : null;

        if ($pago === null || $reserva === null || $reserva->getUsuarioId() !== $usuarioId) {
            throw new PagoNoEncontradoException();
        }

        return $pago;
    }
}
