<?php
namespace App\Application\Abstractions\Auth;

use App\Application\DTOs\Auth\RegistrarUsuarioDTO;
use App\Domain\Entities\Auth\Usuario;

interface IRegistrarUsuarioUseCase
{
    public function execute(RegistrarUsuarioDTO $dto): Usuario;
}
