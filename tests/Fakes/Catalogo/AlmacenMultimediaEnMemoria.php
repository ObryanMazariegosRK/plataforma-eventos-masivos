<?php
namespace Tests\Fakes\Catalogo;

use App\Domain\Abstractions\Catalogo\IAlmacenMultimedia;
use SplFileInfo;

class AlmacenMultimediaEnMemoria implements IAlmacenMultimedia
{
    /** @var array<string, true> rutas guardadas que siguen existiendo */
    public array $archivos = [];
    private int $contador = 0;

    public function guardar(SplFileInfo $archivo, string $carpeta, int $ladoMaximo = 1920): string
    {
        $ruta = $carpeta . '/archivo-' . ++$this->contador . '.' . $archivo->getExtension();
        $this->archivos[$ruta] = true;

        return $ruta;
    }

    public function eliminar(string $ruta): void
    {
        unset($this->archivos[$ruta]);
    }

    public function url(string $ruta): string
    {
        return '/storage/' . $ruta;
    }
}
