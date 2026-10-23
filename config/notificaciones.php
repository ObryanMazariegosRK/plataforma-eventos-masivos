<?php

return [

    // Avisar al personal cuando un evento vendió al menos esta fracción de sus lugares
    // ("casi lleno"); al 100 % el aviso es "agotado"
    'umbral_casi_lleno' => (float) env('NOTIFICACIONES_UMBRAL_CASI_LLENO', 0.9),

    // Bandeja (campana del cliente y sección del panel): avisos por página
    'por_pagina' => 15,

];
