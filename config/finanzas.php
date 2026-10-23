<?php

// Panel → Resumen: ventas, costos y ganancias (solo el administrador las ve)
return [
    // Comisión que cobra la pasarela por cada pago aprobado (0.035 = 3.5 %): se descuenta sola
    'comision_pasarela' => (float) env('FINANZAS_COMISION_PASARELA', 0.035),

    // Días que muestra la gráfica de ventas (se compara con los mismos días anteriores)
    'dias_ventas' => (int) env('FINANZAS_DIAS_VENTAS', 30),

    // Las fechas se guardan en UTC; las ventas se agrupan por día de esta zona
    'zona_horaria' => 'America/Guatemala',

    // Máximo de costos por evento (cada uno es una línea: concepto, categoría y monto)
    'max_costos_por_evento' => 30,
];
