<?php
namespace App\Application\Abstractions\Inventario;

interface IGenerarInventarioEventoUseCase
{
    /** Devuelve cuántos lugares creó (0 si el evento ya tenía inventario). */
    public function execute(int $eventoId): int;
}
