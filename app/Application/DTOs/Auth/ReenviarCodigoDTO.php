<?php
namespace App\Application\DTOs\Auth;

class ReenviarCodigoDTO
{
    public function __construct(
        public readonly string $email,
        public readonly int $minutosValidezCodigo,
    ) {}
}
