<?php
namespace App\Data\Inventario;

use App\Domain\Abstractions\Inventario\IInventarioRepository;
use App\Domain\Entities\Inventario\ItemInventario;
use App\Models\Inventario\ItemInventario as ItemInventarioModel;

class InventarioRepository implements IInventarioRepository
{
    public function buscarPorReserva(int $reservaId, bool $bloquear = false): array
    {
        return ItemInventarioModel::where('reserva_id', $reservaId)
            ->orderBy('id')   // siempre el mismo orden: evita deadlocks entre transacciones
            ->when($bloquear, fn ($q) => $q->lockForUpdate())
            ->get()
            ->map(fn (ItemInventarioModel $m) => $this->aEntidad($m))
            ->all();
    }

    public function guardar(ItemInventario $item): ItemInventario
    {
        $m = $item->getId() ? ItemInventarioModel::findOrFail($item->getId()) : new ItemInventarioModel();
        $m->evento_id       = $item->getEventoId();
        $m->localidad_id    = $item->getLocalidadId();
        $m->asiento_id      = $item->getAsientoId();
        $m->estado          = $item->getEstado();
        $m->reserva_id      = $item->getReservaId();
        $m->bloqueado_hasta = $item->getBloqueadoHasta();
        $m->save();

        return $this->aEntidad($m);
    }

    private function aEntidad(ItemInventarioModel $m): ItemInventario
    {
        return new ItemInventario(
            id: $m->id,
            eventoId: $m->evento_id,
            localidadId: $m->localidad_id,
            asientoId: $m->asiento_id,
            estado: $m->estado,
            reservaId: $m->reserva_id,
            bloqueadoHasta: $m->bloqueado_hasta?->toDateTimeImmutable(),
        );
    }
}
