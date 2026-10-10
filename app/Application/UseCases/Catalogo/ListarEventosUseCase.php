<?php

namespace App\Application\UseCases\Catalogo;

use App\Application\Abstractions\Catalogo\IListarEventosUseCase;
use App\Domain\Abstractions\Catalogo\IEventoRepository;

class ListarEventosUseCase implements IListarEventosUseCase
{
    public function __construct(
        private readonly IEventoRepository $eventoRepository
    ) {
    }

    public function execute(array $filtros = []): array
    {
        return $this->eventoRepository->listarPublicados($filtros);
    }
}
