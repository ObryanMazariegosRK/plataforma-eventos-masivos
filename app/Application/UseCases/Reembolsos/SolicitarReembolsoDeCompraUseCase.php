<?php

namespace App\Application\UseCases\Reembolsos;

use App\Application\Abstractions\Reembolsos\ISolicitarReembolsoDeCompraUseCase;
use App\Domain\Abstractions\Reembolsos\IReembolsoRepository;
use App\Domain\Entities\Reembolsos\Reembolso;
use App\Domain\Exceptions\Pagos\ReservaNoEncontradaException;

/** "Mis compras": el cliente pide el reembolso de una compra (busca su pago aprobado). */
class SolicitarReembolsoDeCompraUseCase implements ISolicitarReembolsoDeCompraUseCase
{
    public function __construct(
        private readonly IReembolsoRepository $reembolsos,
        private readonly SolicitarReembolsoUseCase $solicitar,
    ) {}

    public function execute(int $reservaId, int $usuarioId, ?string $motivo): Reembolso
    {
        $compra = $this->reembolsos->contextoDeReserva($reservaId);
        if ($compra === null || $compra['usuario_id'] !== $usuarioId) {
            throw new ReservaNoEncontradaException;
        }

        $motivo = $motivo === null || trim($motivo) === '' ? null : trim($motivo);

        return $this->solicitar->execute($compra['pago_id'], $usuarioId, $motivo);
    }
}
