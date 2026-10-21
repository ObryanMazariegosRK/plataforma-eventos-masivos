<?php
namespace App\Application\UseCases\Reservas;

use App\Application\Abstractions\Reservas\IConsultarReservaUseCase;
use App\Domain\Abstractions\Reservas\IReservaRepository;
use App\Domain\Exceptions\Pagos\ReservaNoEncontradaException;

class ConsultarReservaUseCase implements IConsultarReservaUseCase
{
    public function __construct(private IReservaRepository $reservas) {}

    public function execute(int $reservaId, int $usuarioId): array
    {
        $detalle = $this->reservas->detalle($reservaId);

        // Reserva de otra persona → mismo error que inexistente (no revela que existe)
        if ($detalle === null || $detalle['usuario_id'] !== $usuarioId) {
            throw new ReservaNoEncontradaException();
        }

        unset($detalle['usuario_id']);

        return $detalle;
    }
}
