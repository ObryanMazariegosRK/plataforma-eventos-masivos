<?php
namespace App\Application\Abstractions\Auth;

use App\Application\DTOs\Auth\SesionDTO;
use App\Application\DTOs\Auth\VerificarCorreoDTO;

interface IVerificarCorreoUseCase
{
    public function execute(VerificarCorreoDTO $dto): SesionDTO;
}
