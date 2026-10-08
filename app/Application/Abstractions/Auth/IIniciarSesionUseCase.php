<?php
namespace App\Application\Abstractions\Auth;

use App\Application\DTOs\Auth\IniciarSesionDTO;
use App\Application\DTOs\Auth\SesionDTO;

interface IIniciarSesionUseCase
{
    public function execute(IniciarSesionDTO $dto): SesionDTO;
}
