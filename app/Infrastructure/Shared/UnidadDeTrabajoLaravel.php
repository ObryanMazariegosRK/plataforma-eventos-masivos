<?php
namespace App\Infrastructure\Shared;

use App\Domain\Abstractions\Shared\IUnidadDeTrabajo;
use Illuminate\Support\Facades\DB;

class UnidadDeTrabajoLaravel implements IUnidadDeTrabajo
{
    public function ejecutar(callable $operacion): mixed
    {
        // Un solo intento a propósito: si la operación habla con una pasarela de pago,
        // reintentarla automáticamente podría intentar cobrar dos veces.
        return DB::transaction(fn () => $operacion(), 1);
    }
}
