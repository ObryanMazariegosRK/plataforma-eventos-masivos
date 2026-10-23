<?php
namespace App\Application\DTOs\Auth;

class VerificarCorreoDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $codigo,
    ) {}
}
