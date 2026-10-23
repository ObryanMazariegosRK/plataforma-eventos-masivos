<?php

namespace Tests\Fakes\Pagos;

use App\Domain\Abstractions\Shared\IUnidadDeTrabajo;
use Throwable;

/** Simula una transacción: si la operación falla, regresa los repositorios a como estaban. */
class UnidadDeTrabajoEnMemoria implements IUnidadDeTrabajo
{
    /** @param list<RepositorioEnMemoria> $repositorios */
    public function __construct(private array $repositorios) {}

    public function ejecutar(callable $operacion): mixed
    {
        $fotos = array_map(fn (RepositorioEnMemoria $r) => $r->instantanea(), $this->repositorios);

        try {
            return $operacion();
        } catch (Throwable $e) {
            foreach ($this->repositorios as $i => $repositorio) {
                $repositorio->restaurar($fotos[$i]);   // "rollback"
            }
            throw $e;
        }
    }
}
