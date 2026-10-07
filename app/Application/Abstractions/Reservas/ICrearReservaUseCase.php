<?php
namespace App\Application\Abstractions\Reservas;

use App\Application\DTOs\Reservas\CrearReservaDTO;
use App\Domain\Entities\Reservas\Reserva;

interface ICrearReservaUseCase
{
    public function execute(CrearReservaDTO $dto): Reserva;
}
