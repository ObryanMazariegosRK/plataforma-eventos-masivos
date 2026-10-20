<?php
namespace App\Application\UseCases\Inventario;

use App\Application\Abstractions\Inventario\IConsultarDisponibilidadUseCase;
use App\Domain\Abstractions\Inventario\IInventarioRepository;
use App\Domain\Exceptions\Inventario\EventoNoDisponibleException;

class ConsultarDisponibilidadUseCase implements IConsultarDisponibilidadUseCase
{
    public function __construct(
        private IInventarioRepository $inventario,
    ) {}

    public function execute(int $eventoId): array
    {
        return $this->inventario->disponibilidad($eventoId)
            ?? throw new EventoNoDisponibleException();
    }
}
