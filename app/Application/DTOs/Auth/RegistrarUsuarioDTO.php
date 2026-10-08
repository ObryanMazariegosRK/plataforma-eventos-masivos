<?php
namespace App\Application\DTOs\Auth;

class RegistrarUsuarioDTO
{
    public function __construct(
        public readonly string $nombre,
        public readonly string $apellido,
        public readonly string $email,
        public readonly string $password,
        public readonly int $minutosValidezCodigo,
        public readonly ?string $telefono = null,
    ) {}
}
