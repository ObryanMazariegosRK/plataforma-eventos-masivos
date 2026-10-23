<?php

namespace App\Application\Abstractions\Reembolsos;

interface IListarReembolsosAdminUseCase
{
    /** @return array{reembolsos: list<array>, por_estado: list<array>, meta: array} */
    public function execute(?string $estado, int $pagina, int $porPagina): array;
}
