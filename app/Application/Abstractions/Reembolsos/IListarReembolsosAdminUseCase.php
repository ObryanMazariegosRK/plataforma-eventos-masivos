<?php

namespace App\Application\Abstractions\Reembolsos;

interface IListarReembolsosAdminUseCase
{
    /** @return array{reembolsos: list<array>, por_estado: list<array>, meta: array} */
    /** $desde: solicitudes hechas desde ese instante (UTC); null = todas */
    public function execute(?string $estado, ?\DateTimeImmutable $desde, int $pagina, int $porPagina): array;
}
