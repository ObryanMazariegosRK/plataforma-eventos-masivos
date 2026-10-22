<?php

// Bitácora: quién hizo qué y cuándo (inicios de sesión, compras, cambios en eventos y usuarios...).
// Vive en su propia base de datos (connections "bitacora" y "bitacora_admin" en config/database.php);
// se instala una vez con `php artisan bitacora:instalar`.
return [

    // Si es false no se escribe nada (los tests la apagan salvo los de la bitácora)
    'habilitada' => (bool) env('BITACORA_HABILITADA', true),

    // Las fechas se guardan en UTC; el panel las muestra y las filtra con esta zona
    'zona_horaria' => 'America/Guatemala',

    // Registros por tanda en el panel ("Ver más" trae la siguiente)
    'por_pagina' => (int) env('BITACORA_POR_PAGINA', 25),
];
