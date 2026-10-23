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

    // ---- Generación y consulta: valores configurables desde la prueba ----

    /** @var list<string> localidades sin precio que devolverá localidadesSinPrecio() */
    public array $sinPrecio = [];

    /** cuántos lugares "creará" generarParaEvento() */
    public int $lugaresDelRecinto = 0;

    /** @var list<int> eventos para los que se llamó generarParaEvento() */
    public array $generados = [];

    public function existeParaEvento(int $eventoId): bool
    {
        return in_array($eventoId, $this->generados, true)
            || (bool) array_filter($this->filas, fn (ItemInventario $i) => $i->getEventoId() === $eventoId);
    }

    public function localidadesSinPrecio(int $eventoId): array { return $this->sinPrecio; }

    public function generarParaEvento(int $eventoId): int
    {
        $this->generados[] = $eventoId;

        return $this->lugaresDelRecinto;
    }

    public function disponibilidad(int $eventoId): ?array { return null; }
    public function planoRecinto(int $recintoId): ?array { return null; }
}
