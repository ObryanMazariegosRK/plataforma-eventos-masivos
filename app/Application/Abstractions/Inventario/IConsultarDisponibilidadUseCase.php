<?php
namespace App\Application\Abstractions\Inventario;

interface IConsultarDisponibilidadUseCase
{
    /** Ver la estructura en IInventarioRepository::disponibilidad(). */
    public function execute(int $eventoId): array;
}
