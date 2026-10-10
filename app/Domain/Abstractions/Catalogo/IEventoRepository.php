<?php

namespace App\Domain\Abstractions\Catalogo;

interface IEventoRepository
{
    public function listarPublicados(
        array $filtros = []
    ): array;
}
