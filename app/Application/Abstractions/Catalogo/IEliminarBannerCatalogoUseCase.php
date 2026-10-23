<?php
namespace App\Application\Abstractions\Catalogo;

interface IEliminarBannerCatalogoUseCase
{
    // Quita el banner: el catálogo vuelve a la foto por defecto
    public function execute(): void;
}
