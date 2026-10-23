<?php
namespace App\Application\DTOs\Auth;

class SolicitarRecuperacionDTO
{
    public function __construct(
        public readonly string $email,
        public readonly int $minutosValidezCodigo,
    ) {}
}
