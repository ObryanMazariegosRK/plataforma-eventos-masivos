<?php
namespace App\Application\Abstractions\Pagos;

use App\Application\DTOs\Pagos\IniciarPagoDTO;
use App\Application\DTOs\Pagos\PagoIniciadoDTO;

interface IIniciarPagoUseCase
{
    public function execute(IniciarPagoDTO $dto): PagoIniciadoDTO;
}
