<?php
namespace App\Application\DTOs\Auth;

class IniciarSesionDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly bool $recordar = false,
    ) {}
}
