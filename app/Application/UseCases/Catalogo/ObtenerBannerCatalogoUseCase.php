<?php
namespace App\Application\UseCases\Catalogo;

use App\Application\Abstractions\Catalogo\IObtenerBannerCatalogoUseCase;
use App\Domain\Abstractions\Catalogo\IAlmacenMultimedia;
use App\Domain\Abstractions\Catalogo\IBannerRepository;

class ObtenerBannerCatalogoUseCase implements IObtenerBannerCatalogoUseCase
{
    public function __construct(
        private IBannerRepository $banners,
        private IAlmacenMultimedia $almacen,
    ) {}

    public function execute(): ?array
    {
        $banner = $this->banners->actual();

        return $banner === null ? null : [
            'tipo' => $banner->getTipo()->value,
            'url'  => $this->almacen->url($banner->getRuta()),
        ];
    }
}
