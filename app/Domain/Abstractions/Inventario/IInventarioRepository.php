<?php
namespace App\Domain\Abstractions\Inventario;

use App\Domain\Entities\Inventario\ItemInventario;

interface IInventarioRepository
{
    /**
     * Asientos/cupos que pertenecen a una reserva.
     * Con $bloquear = true, las filas quedan bloqueadas (FOR UPDATE) hasta el fin de la transacción.
     *
     * @return list<ItemInventario>
     */
    public function buscarPorReserva(int $reservaId, bool $bloquear = false): array;

    public function guardar(ItemInventario $item): ItemInventario;
}
