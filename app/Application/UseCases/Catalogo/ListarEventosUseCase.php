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

    public function execute(array $filtros = [], ?int $pagina = null, ?int $porPagina = null): array
    {
        if ($pagina === null || $porPagina === null) {
            $eventos = $this->eventoRepository->listarPublicados($filtros);

            return ['eventos' => $eventos, 'total' => count($eventos)];
        }

        return [
            'eventos' => $this->eventoRepository->listarPublicados($filtros, $porPagina, ($pagina - 1) * $porPagina),
            'total'   => $this->eventoRepository->contarPublicados($filtros),
        ];
    }
}
