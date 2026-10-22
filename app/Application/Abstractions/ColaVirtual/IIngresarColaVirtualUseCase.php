<?php

namespace App\Application\Abstractions\ColaVirtual;

use App\Domain\Entities\ColaVirtual\Turno;

interface IIngresarColaVirtualUseCase
{
    public function execute(
        int $eventoId,
        int $usuarioId
    ): Turno;
}
