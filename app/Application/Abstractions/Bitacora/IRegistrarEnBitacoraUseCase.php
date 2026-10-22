<?php

namespace App\Application\Abstractions\Bitacora;

use App\Application\DTOs\Bitacora\RegistrarEnBitacoraDTO;

interface IRegistrarEnBitacoraUseCase
{
    public function execute(RegistrarEnBitacoraDTO $dto): void;
}
