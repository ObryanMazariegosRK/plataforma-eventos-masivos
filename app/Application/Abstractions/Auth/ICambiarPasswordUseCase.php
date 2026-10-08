<?php
namespace App\Application\Abstractions\Auth;

use App\Application\DTOs\Auth\CambiarPasswordDTO;

interface ICambiarPasswordUseCase
{
    public function execute(CambiarPasswordDTO $dto): void;
}
