<?php

namespace Tests\Fakes\Pagos;

use App\Domain\Abstractions\Inventario\IInventarioRepository;
use App\Domain\Entities\Inventario\ItemInventario;

class InventarioRepositoryEnMemoria extends RepositorioEnMemoria implements IInventarioRepository
{
    public function buscarPorReserva(int $reservaId, bool $bloquear = false): array
    {
        return array_values(array_map(
            fn ($i) => clone $i,
            array_filter($this->filas, fn (ItemInventario $i) => $i->getReservaId() === $reservaId),
        ));
    }

    public function guardar(ItemInventario $item): ItemInventario { return $this->insertarOActualizar($item); }
}
