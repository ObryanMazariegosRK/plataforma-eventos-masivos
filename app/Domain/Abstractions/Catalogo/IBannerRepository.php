<?php
namespace App\Domain\Abstractions\Catalogo;

use App\Domain\Entities\Catalogo\Banner;

interface IBannerRepository
{
    // El banner vigente, o null si se usa la foto por defecto
    public function actual(): ?Banner;

    // Sustituye el banner vigente (solo existe uno) y lo devuelve con su id
    public function reemplazar(Banner $banner): Banner;

    public function eliminar(): void;
}
