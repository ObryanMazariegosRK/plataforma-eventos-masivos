<?php

return [

    // Banner de la portada del catálogo (panel de admin → Banner)
    // Eventos por tanda en la cartelera ("Ver más funciones" trae otra tanda)
    'eventos_por_pagina' => (int) env('CATALOGO_EVENTOS_POR_PAGINA', 6),

    // Panel → "Catálogo de eventos": tarjetas por tanda
    'admin_eventos_por_pagina' => (int) env('CATALOGO_ADMIN_EVENTOS_POR_PAGINA', 12),

    // Fecha de las funciones: se escribe en hora de Guatemala (se guarda en UTC) y se puede
    // programar como máximo con estos meses de anticipación
    'zona_horaria' => 'America/Guatemala',
    'max_meses_evento' => (int) env('CATALOGO_MAX_MESES_EVENTO', 3),

    // Fotos de cada evento (se suben desde el formulario del panel)
    'imagen_evento_max_mb' => (int) env('IMAGEN_EVENTO_MAX_MB', 8),

    'banner' => [
        'max_mb'            => (int) env('BANNER_MAX_MB', 15),            // peso máximo de la imagen o el video
        'max_segundos_video' => (int) env('BANNER_MAX_SEGUNDOS_VIDEO', 30), // duración máxima (se revisa en el navegador)
    ],

];
