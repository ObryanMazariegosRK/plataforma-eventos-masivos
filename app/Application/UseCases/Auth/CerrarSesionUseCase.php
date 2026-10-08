<?php
namespace App\Application\UseCases\Auth;

use App\Application\Abstractions\Auth\ICerrarSesionUseCase;
use App\Domain\Abstractions\Auth\ITokenService;

class CerrarSesionUseCase implements ICerrarSesionUseCase
{
    public function __construct(
        private ITokenService $tokens,
    ) {}

    public function execute(int $tokenId): void
    {
        // Solo se revoca el token de esta petición: las sesiones en otros dispositivos siguen activas.
        $this->tokens->revocar($tokenId);
    }
}
