<?php
namespace App\Application\DTOs\Auth;

class CambiarPasswordDTO
{
    public function __construct(
        public readonly int $usuarioId,
        public readonly string $passwordActual,
        public readonly string $passwordNueva,
        public readonly int $tokenActualId,   // la sesión que se conserva
    ) {}
}
