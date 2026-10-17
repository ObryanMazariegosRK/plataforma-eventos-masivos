<?php

namespace App\Domain\Abstractions\Transacciones;

use App\Domain\Entities\Transacciones\Transaccion;

interface ITransaccionRepository
{
    public function registrar(
        Transaccion $transaccion,
        ?int $registradoPor = null
    ): Transaccion;
}
