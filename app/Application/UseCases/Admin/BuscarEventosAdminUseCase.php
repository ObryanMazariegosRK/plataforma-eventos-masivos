<?php
namespace App\Application\UseCases\Admin;

use App\Application\Abstractions\Admin\IBuscarEventosAdminUseCase;
use App\Domain\Abstractions\Admin\ICatalogoEventosAdminRepository;

class BuscarEventosAdminUseCase implements IBuscarEventosAdminUseCase
{
    public function __construct(private ICatalogoEventosAdminRepository $eventos) {}

    public function execute(array $filtros, int $pagina, int $porPagina): array
    {
        return [
            'eventos' => $this->eventos->buscar($filtros, $porPagina, ($pagina - 1) * $porPagina),
            'total'   => $this->eventos->contar($filtros),
        ];
    }
}
