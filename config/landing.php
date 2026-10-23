<?php

// Contenido del recorrido por scroll de la experiencia de cada evento (/eventos/{id}/experiencia).
//   foco        → punto de la foto hacia el que se acerca la cámara (x% y%), en pantallas horizontales
//   focoMovil   → lo mismo en pantallas verticales (celular), donde la foto se recorta por los lados
//   zoomInicial → acercamiento con el que empieza la parada (1 = foto completa)

return [

    'paradas' => [
    [
        'imagen'      => 'img/landing/auditorio-general.jpg',
        'alt'         => 'Vista general del teatro desde el balcón, con arcos iluminados y el escenario al fondo',
        'foco'        => '50% 60%',   // hacia el escenario
        'focoMovil'   => '50% 60%',
        'zoomInicial' => 1,
        'etiqueta'    => 'La llegada',
        'titulo'      => 'Esta noche, la función es tuya.',
        'texto'       => 'Las puertas están por abrirse. Miles de personas buscan su lugar al mismo tiempo.',
    ],
    [
        'imagen'      => 'img/landing/auditorio-butacas.jpg',
        'alt'         => 'Butacas del teatro con el escenario iluminado en azul a la izquierda',
        'foco'        => '35% 72%',   // hacia las butacas y el escenario
        'focoMovil'   => '22% 70%',   // en vertical, el escenario azul queda a la vista
        'zoomInicial' => 1,
        'etiqueta'    => 'Tu butaca',
        'titulo'      => 'Elige tu asiento.',
        'texto'       => 'Lo apartamos solo para ti durante 5 minutos mientras completas tu compra. Nadie más puede tomarlo.',
    ],
    [
        // la misma vista general, pero ya cerca del escenario: la foto es grande y aguanta el acercamiento
        'imagen'      => 'img/landing/auditorio-general.jpg',
        'alt'         => 'El escenario del teatro visto de cerca',
        'foco'        => '50% 64%',
        'focoMovil'   => '50% 64%',
        'zoomInicial' => 1.7,
        'etiqueta'    => 'Se abre el telón',
        'titulo'      => 'Tu boleto, en tu correo.',
        'texto'       => 'Pago seguro, boleto digital con código QR y cero sobreventa.',
    ],
],

];
