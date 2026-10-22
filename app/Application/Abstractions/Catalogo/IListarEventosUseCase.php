<?php

namespace App\Application\Abstractions\Catalogo;

interface IListarEventosUseCase
{
    /**
     * ['eventos' => [...], 'total' => n]. Con $pagina y $porPagina devuelve solo esa tanda;
     * sin ellos, todos los eventos (como antes).
     */
    public function execute(array $filtros = [], ?int $pagina = null, ?int $porPagina = null): array;
}
