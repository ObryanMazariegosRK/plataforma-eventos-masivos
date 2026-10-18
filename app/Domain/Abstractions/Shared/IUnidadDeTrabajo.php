<?php
namespace App\Domain\Abstractions\Shared;

/**
 * "Haz todo esto o nada": ejecuta el callback dentro de una transacción de base de datos.
 * Si el callback lanza una excepción, se deshace todo lo que hizo.
 *
 * Existe para que los casos de uso no dependan de DB::transaction (Laravel).
 */
interface IUnidadDeTrabajo
{
    /**
     * @template T
     * @param callable(): T $operacion
     * @return T
     */
    public function ejecutar(callable $operacion): mixed;
}
