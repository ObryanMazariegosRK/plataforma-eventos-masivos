<?php

return [

    // Nombre comercial de la plataforma: se muestra en las vistas (logo, títulos, correos).
    // Úsenlo con config('marca.nombre') en lugar de escribir el nombre a mano.
    'nombre' => env('MARCA_NOMBRE', 'Soirée'),

    // Correo para dudas de privacidad y soporte (pie de página y /privacidad).
    // El valor por defecto usa el dominio reservado .test: cámbienlo en .env por uno real.
    'contacto' => env('MARCA_CONTACTO', 'privacidad@soiree.test'),

];
