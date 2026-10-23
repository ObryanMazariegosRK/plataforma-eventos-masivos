<?php

namespace Tests\Fakes\Pagos;

use App\Domain\Abstractions\Pagos\IPagoRepository;
use App\Domain\Entities\Pagos\Pago;

class PagoRepositoryEnMemoria extends RepositorioEnMemoria implements IPagoRepository
{
    public function guardar(Pago $pago): Pago { return $this->insertarOActualizar($pago); }
    public function buscarPorId(int $id): ?Pago { return $this->obtener($id); }
    public function buscarPorIdParaActualizar(int $id): ?Pago { return $this->obtener($id); }

    public function contarPorReserva(int $reservaId): int
    {
        return count(array_filter($this->filas, fn (Pago $p) => $p->getReservaId() === $reservaId));
    }

    public function existeAprobadoParaReserva(int $reservaId): bool
    {
        return (bool) array_filter($this->filas, fn (Pago $p) => $p->getReservaId() === $reservaId && $p->fueAprobado());
    }
}
