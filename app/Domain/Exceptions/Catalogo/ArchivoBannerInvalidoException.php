<?php
namespace App\Domain\Exceptions\Catalogo;

use App\Domain\Exceptions\ReglaDeNegocioException;

class ArchivoBannerInvalidoException extends ReglaDeNegocioException
{
    public function __construct(string $mensaje = 'El banner debe ser una imagen (JPG, PNG, WEBP) o un video (MP4, WEBM).')
    {
        parent::__construct($mensaje);
    }
}
