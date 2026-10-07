<?php
namespace App\Domain\Abstractions\Reservas;

use App\Domain\Entities\Reservas\Reserva;

interface IReservaRepository
{
    public function guardar(Reserva $reserva): Reserva;

    public function buscarPorId(int $id): ?Reserva;
}
