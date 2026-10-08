<?php
namespace App\Application\Abstractions\Auth;

use App\Domain\Entities\Auth\Usuario;

interface IObtenerPerfilUseCase
{
    public function execute(int $usuarioId): Usuario;
}
