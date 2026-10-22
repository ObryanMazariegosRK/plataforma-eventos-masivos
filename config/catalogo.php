<?php

return [

    // Banner de la portada del catálogo (panel de admin → Banner)
    // Eventos por tanda en la cartelera ("Ver más funciones" trae otra tanda)
    'eventos_por_pagina' => (int) env('CATALOGO_EVENTOS_POR_PAGINA', 6),

    'banner' => [
        'max_mb'            => (int) env('BANNER_MAX_MB', 15),            // peso máximo de la imagen o el video
        'max_segundos_video' => (int) env('BANNER_MAX_SEGUNDOS_VIDEO', 30), // duración máxima (se revisa en el navegador)
    ],

];
