<?php

namespace App\Application\Abstractions\Admin;

use SplFileInfo;

interface ISubirImagenEventoUseCase
{
    // Guarda la foto (reducida y en WebP) y devuelve su URL pública (/storage/eventos/...)
    public function execute(SplFileInfo $archivo): string;
}
