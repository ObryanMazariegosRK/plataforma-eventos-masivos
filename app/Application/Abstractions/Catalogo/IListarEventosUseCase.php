<?php

namespace App\Application\Abstractions\Catalogo;

interface IListarEventosUseCase
{
    public function execute(array $filtros = []): array;
}
