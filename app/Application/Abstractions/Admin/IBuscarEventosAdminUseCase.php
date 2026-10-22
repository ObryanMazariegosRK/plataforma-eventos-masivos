<?php
namespace App\Application\Abstractions\Admin;

interface IBuscarEventosAdminUseCase
{
    // Catálogo de eventos del panel: ['eventos' => [...], 'total' => n] de la página pedida
    public function execute(array $filtros, int $pagina, int $porPagina): array;
}
