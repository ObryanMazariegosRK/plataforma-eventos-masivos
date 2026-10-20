<?php
namespace App\Application\DTOs\Catalogo;

use SplFileInfo;

readonly class ActualizarBannerDTO
{
    public function __construct(
        public SplFileInfo $archivo,
        public string $tipoMime,
        public int $usuarioId,
    ) {}
}
