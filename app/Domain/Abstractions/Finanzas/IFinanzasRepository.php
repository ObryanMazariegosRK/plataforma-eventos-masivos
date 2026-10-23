<?php

namespace App\Domain\Abstractions\Finanzas;

interface IFinanzasRepository
{
    /**
     * Pagos aprobados cuyo cobro ocurrió entre $desde y $hasta (UTC, "hasta" exclusivo):
     * [{pagado_en (UTC 'Y-m-d H:i:s'), monto, boletos}].
     */
    public function pagosAprobadosEntre(string $desde, string $hasta): array;

    /**
     * Una fila por evento que ya salió a la venta (no borradores):
     * [{id, nombre, estado, fecha_evento (UTC), ingresos_brutos, reembolsos, costos,
     *   vendidos, lugares, precio_promedio}].
     */
    public function resultadosPorEvento(): array;
}
