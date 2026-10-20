<?php

return [

    // Tiempo (en segundos) que una reserva ACTIVA retiene los asientos antes de vencer.
    'ttl_segundos' => (int) env('RESERVA_TTL_SEGUNDOS', 300),

    // Máximo de lugares (asientos + cupos) en una sola reserva: evita que una cuenta acapare la localidad.
    'max_asientos_por_reserva' => (int) env('RESERVA_MAX_ASIENTOS', 6),

];
