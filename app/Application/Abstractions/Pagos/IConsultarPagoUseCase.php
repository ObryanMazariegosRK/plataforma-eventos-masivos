<?php
namespace App\Application\Abstractions\Pagos;

use App\Domain\Entities\Pagos\Pago;

interface IConsultarPagoUseCase
{
    public function execute(int $usuarioId, int $pagoId): Pago;
}
