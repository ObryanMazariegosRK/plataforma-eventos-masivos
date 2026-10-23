<?php

namespace App\Application\Abstractions\Bitacora;

use App\Application\DTOs\Bitacora\BuscarEnBitacoraDTO;

interface IBuscarEnBitacoraUseCase
{
    // ['registros' => [...], 'total' => n] de la página pedida, del más reciente al más antiguo
    public function execute(BuscarEnBitacoraDTO $dto): array;
}
