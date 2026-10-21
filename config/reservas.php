<?php

return [

    // Tiempo (en segundos) que una reserva ACTIVA retiene los asientos antes de vencer.
    'ttl_segundos' => (int) env('RESERVA_TTL_SEGUNDOS', 300),

    // Máximo de boletos por persona en un mismo evento: suma lo ya pagado y lo apartado que aún
    // no vence, así nadie acapara el recinto repitiendo compras (como "6 por persona" en Ticketmaster).
    'max_boletos_por_persona' => (int) env('RESERVA_MAX_POR_PERSONA', 5),

];
