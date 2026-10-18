<?php

namespace Tests\Fakes\Pagos;

/**
 * Base de los repositorios falsos: guarda COPIAS de las entidades (como hace la BD)
 * y permite sacar una "foto" de su contenido para simular el rollback de una transacción.
 */
abstract class RepositorioEnMemoria
{
    /** @var array<int, object> */
    public array $filas = [];

    protected function insertarOActualizar(object $entidad): object
    {
        $id = $entidad->getId() ?? (count($this->filas) ? max(array_keys($this->filas)) + 1 : 1);

        $copia = clone $entidad;
        (fn () => $this->id = $id)->call($copia);   // asigna el id privado, solo en pruebas
        $this->filas[$id] = $copia;

        return clone $copia;
    }

    protected function obtener(int $id): ?object
    {
        return isset($this->filas[$id]) ? clone $this->filas[$id] : null;
    }

    public function instantanea(): array
    {
        return array_map(fn ($e) => clone $e, $this->filas);
    }

    public function restaurar(array $instantanea): void
    {
        $this->filas = $instantanea;
    }
}
