<?php
namespace App\Application\DTOs\Auth;

// Datos que Google devuelve sobre la persona (los arma el controller con Socialite).
class IniciarSesionConGoogleDTO
{
    public function __construct(
        public readonly string $googleId,
        public readonly string $email,
        public readonly bool $emailVerificado,   // Google confirma que el correo es de esa persona
        public readonly string $nombre,
        public readonly string $apellido,
    ) {}
}
