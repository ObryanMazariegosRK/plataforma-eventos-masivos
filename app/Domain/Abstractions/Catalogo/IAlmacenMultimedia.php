<?php
namespace App\Domain\Abstractions\Catalogo;

use SplFileInfo;

interface IAlmacenMultimedia
{
    /**
     * Guarda el archivo en la carpeta y devuelve su ruta relativa. Las imágenes se reducen
     * a $ladoMaximo px por su lado mayor y se comprimen; los videos se guardan tal cual.
     * Lanza RuntimeException si no se pudo escribir.
     */
    public function guardar(SplFileInfo $archivo, string $carpeta, int $ladoMaximo = 1920): string;

    public function eliminar(string $ruta): void;

    // URL pública del archivo (p. ej. /storage/banners/xxx.webp)
    public function url(string $ruta): string;
}
