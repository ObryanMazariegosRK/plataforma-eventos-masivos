<?php
namespace App\Application\Abstractions\Catalogo;

use App\Application\DTOs\Catalogo\ActualizarBannerDTO;

interface IActualizarBannerCatalogoUseCase
{
    // Sube la imagen o el video y lo deja como banner del catálogo. Devuelve {tipo, url}
    public function execute(ActualizarBannerDTO $dto): array;
}
