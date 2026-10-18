<?php

namespace Tests\Fakes\Pagos;

use App\Domain\Abstractions\Pasarelas\IPasarelaRepository;
use App\Domain\Entities\Pasarelas\Pasarela;

class PasarelaRepositoryEnMemoria extends RepositorioEnMemoria implements IPasarelaRepository
{
    public function agregar(Pasarela $pasarela): Pasarela { return $this->insertarOActualizar($pasarela); }
    public function buscarPorId(int $id): ?Pasarela { return $this->obtener($id); }

    public function buscarActivaPorCodigo(string $codigo): ?Pasarela
    {
        foreach ($this->filas as $p) {
            if ($p->getCodigo() === $codigo && $p->estaActiva()) {
                return clone $p;
            }
        }

        return null;
    }

    public function listarActivas(): array
    {
        return array_values(array_filter($this->filas, fn (Pasarela $p) => $p->estaActiva()));
    }
}
