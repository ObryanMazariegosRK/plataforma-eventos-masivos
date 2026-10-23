<?php

namespace App\Application\Abstractions\Finanzas;

use App\Application\DTOs\Finanzas\ObtenerFinanzasDTO;

interface IObtenerFinanzasUseCase
{
    /**
     * Ventas por día del período, totales contra el período anterior, ganancia del mes y
     * rentabilidad de cada evento (ingresos netos, costos, ganancia, punto de equilibrio).
     */
    public function execute(ObtenerFinanzasDTO $dto): array;
}
