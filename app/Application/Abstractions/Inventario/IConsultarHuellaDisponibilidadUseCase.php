<?php
namespace App\Application\Abstractions\Inventario;

interface IConsultarHuellaDisponibilidadUseCase
{
    /** Ver IInventarioRepository::huellaDisponibilidad(). Lanza EventoNoDisponibleException si no está a la venta. */
    public function execute(int $eventoId): string;
}
