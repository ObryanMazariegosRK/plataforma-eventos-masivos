<?php

namespace Tests\Fakes\Pagos;

use App\Domain\Abstractions\Reservas\IReservaRepository;
use App\Domain\Entities\Reservas\Reserva;

class ReservaRepositoryEnMemoria extends RepositorioEnMemoria implements IReservaRepository
{
    public function guardar(Reserva $reserva): Reserva { return $this->insertarOActualizar($reserva); }
    public function buscarPorId(int $id): ?Reserva { return $this->obtener($id); }
    public function buscarPorIdParaActualizar(int $id): ?Reserva { return $this->obtener($id); }
}
