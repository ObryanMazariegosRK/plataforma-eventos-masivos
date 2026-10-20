<?php
namespace App\Application\UseCases\Catalogo;

use App\Application\Abstractions\Catalogo\IEliminarBannerCatalogoUseCase;
use App\Domain\Abstractions\Catalogo\IAlmacenMultimedia;
use App\Domain\Abstractions\Catalogo\IBannerRepository;

class EliminarBannerCatalogoUseCase implements IEliminarBannerCatalogoUseCase
{
    public function __construct(
        private IBannerRepository $banners,
        private IAlmacenMultimedia $almacen,
    ) {}

    public function execute(): void
    {
        $actual = $this->banners->actual();
        if ($actual === null) {
            return;   // ya está la foto por defecto
        }

        $this->banners->eliminar();
        $this->almacen->eliminar($actual->getRuta());
    }
}
