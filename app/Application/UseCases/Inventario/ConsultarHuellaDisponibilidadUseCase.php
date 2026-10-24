<?php
namespace App\Application\UseCases\Inventario;

use App\Application\Abstractions\Inventario\IConsultarHuellaDisponibilidadUseCase;
use App\Domain\Abstractions\Inventario\IInventarioRepository;
use App\Domain\Exceptions\Inventario\EventoNoDisponibleException;

class ConsultarHuellaDisponibilidadUseCase implements IConsultarHuellaDisponibilidadUseCase
{
    public function __construct(private IInventarioRepository $inventario) {}

    public function execute(int $eventoId): string
    {
        return $this->inventario->huellaDisponibilidad($eventoId)
            ?? throw new EventoNoDisponibleException();
    }
}
