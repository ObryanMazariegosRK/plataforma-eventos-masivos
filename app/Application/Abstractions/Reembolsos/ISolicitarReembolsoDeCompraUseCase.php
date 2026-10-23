<?php

namespace App\Application\Abstractions\Reembolsos;

use App\Domain\Entities\Reembolsos\Reembolso;

interface ISolicitarReembolsoDeCompraUseCase
{
    public function execute(int $reservaId, int $usuarioId, ?string $motivo): Reembolso;
}
