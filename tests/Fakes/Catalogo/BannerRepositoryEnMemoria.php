<?php
namespace Tests\Fakes\Catalogo;

use App\Domain\Abstractions\Catalogo\IBannerRepository;
use App\Domain\Entities\Catalogo\Banner;

class BannerRepositoryEnMemoria implements IBannerRepository
{
    public ?Banner $banner = null;
    public bool $fallarAlGuardar = false;

    public function actual(): ?Banner
    {
        return $this->banner;
    }

    public function reemplazar(Banner $banner): Banner
    {
        if ($this->fallarAlGuardar) {
            throw new \RuntimeException('La base de datos no responde.');
        }

        return $this->banner = new Banner(1, $banner->getTipo(), $banner->getRuta(), $banner->getActualizadoPor());
    }

    public function eliminar(): void
    {
        $this->banner = null;
    }
}
