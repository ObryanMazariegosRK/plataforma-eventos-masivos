<?php
namespace App\Application\Abstractions\Catalogo;

interface IObtenerBannerCatalogoUseCase
{
    // {tipo: imagen|video, url} o null si se usa la foto por defecto
    public function execute(): ?array;
}
