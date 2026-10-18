<?php

return [

    // Moneda en la que se cobra.
    'moneda' => env('PAGOS_MONEDA', 'GTQ'),

    'simulada' => [
        // Minutos que vive una orden de la pasarela simulada antes de vencer.
        'minutos_orden' => (int) env('PAGOS_SIMULADA_MINUTOS_ORDEN', 30),
    ],

];
