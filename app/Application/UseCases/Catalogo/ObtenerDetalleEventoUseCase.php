<?php

namespace App\Application\UseCases\Catalogo;

use App\Domain\Abstractions\Catalogo\IEventoRepository;

class ObtenerDetalleEventoUseCase
{
    public function __construct(
        private readonly IEventoRepository $eventos
    ) {
    }

    public function execute(int $id): ?array
    {
        return $this->eventos->obtenerPublicadoPorId($id);
    }
}
