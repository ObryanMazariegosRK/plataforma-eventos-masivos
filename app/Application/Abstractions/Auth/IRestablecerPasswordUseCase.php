<?php
namespace App\Application\Abstractions\Auth;

use App\Application\DTOs\Auth\RestablecerPasswordDTO;

interface IRestablecerPasswordUseCase
{
    public function execute(RestablecerPasswordDTO $dto): void;
}
