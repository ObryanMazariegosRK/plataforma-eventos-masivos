<?php

namespace App\Application\UseCases\Reembolsos;

use App\Application\Abstractions\Reembolsos\ISolicitarReembolsoDeCompraUseCase;
use App\Application\UseCases\Notificaciones\NotificarPersonalUseCase;
use App\Domain\Abstractions\Reembolsos\IReembolsoRepository;
use App\Domain\Entities\Reembolsos\Reembolso;
use App\Domain\Exceptions\Pagos\ReservaNoEncontradaException;

/** "Mis compras": el cliente pide el reembolso de una compra (busca su pago aprobado). */
class SolicitarReembolsoDeCompraUseCase implements ISolicitarReembolsoDeCompraUseCase
{
    public function __construct(
        private readonly IReembolsoRepository $reembolsos,
        private readonly SolicitarReembolsoUseCase $solicitar,
        private readonly NotificarPersonalUseCase $personal,
    ) {}

    public function execute(int $reservaId, int $usuarioId, ?string $motivo): Reembolso
    {
        $compra = $this->reembolsos->contextoDeReserva($reservaId);
        if ($compra === null || $compra['usuario_id'] !== $usuarioId) {
            throw new ReservaNoEncontradaException;
        }

        $motivo = $motivo === null || trim($motivo) === '' ? null : trim($motivo);

        $reembolso = $this->solicitar->execute($compra['pago_id'], $usuarioId, $motivo);

        // La solicitud ya quedó: si el aviso al personal falla, no se deshace
        try {
            $this->personal->reembolsoSolicitado($reembolso->getId());
        } catch (\Throwable $e) {
            report($e);
        }

        return $reembolso;
    }
}
