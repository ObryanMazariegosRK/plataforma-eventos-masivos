<?php
namespace App\Application\Abstractions\Pagos;

use App\Application\DTOs\Pagos\ConfirmarPagoDTO;
use App\Domain\Entities\Pagos\Pago;

interface IConfirmarPagoUseCase
{
    public function execute(ConfirmarPagoDTO $dto): Pago;
}
