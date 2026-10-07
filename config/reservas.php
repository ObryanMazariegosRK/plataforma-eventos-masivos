<?php

return [

    // Tiempo (en segundos) que una reserva ACTIVA retiene los asientos antes de vencer.
    'ttl_segundos' => (int) env('RESERVA_TTL_SEGUNDOS', 300),

];
