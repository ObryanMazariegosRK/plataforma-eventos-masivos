<?php
namespace App\Application\Abstractions\Auth;

use App\Application\DTOs\Auth\ReenviarCodigoDTO;

interface IReenviarCodigoUseCase
{
    public function execute(ReenviarCodigoDTO $dto): void;
}
