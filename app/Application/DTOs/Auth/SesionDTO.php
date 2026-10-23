<?php
namespace App\Application\DTOs\Auth;

use App\Domain\Entities\Auth\Usuario;

// DTO de SALIDA: lo que devuelve un caso de uso que abre sesión.
class SesionDTO
{
    public function __construct(
        public readonly string $token,
        public readonly Usuario $usuario,
    ) {}
}
