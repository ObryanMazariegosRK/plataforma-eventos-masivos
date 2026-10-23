<?php
namespace App\Application\DTOs\Auth;

class IniciarSesionDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        // true = acceso del personal (/admin/login); false = tienda (/login)
        public readonly bool $personal = false,
    ) {}
}
