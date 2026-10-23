<?php
namespace App\Application\Abstractions\Auth;

use App\Application\DTOs\Auth\IniciarSesionConGoogleDTO;
use App\Application\DTOs\Auth\SesionDTO;

interface IIniciarSesionConGoogleUseCase
{
    public function execute(IniciarSesionConGoogleDTO $dto): SesionDTO;
}
