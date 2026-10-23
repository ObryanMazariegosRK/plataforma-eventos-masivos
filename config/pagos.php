<?php

return [

    // Moneda en la que se cobra.
    'moneda' => env('PAGOS_MONEDA', 'GTQ'),

    'simulada' => [
        // Tope de minutos para pagar una orden. En la práctica vence antes: junto con la
        // reserva (reservas.ttl_segundos, 5 min), que es lo que se pasa al crear la orden.
        'minutos_orden' => (int) env('PAGOS_SIMULADA_MINUTOS_ORDEN', 30),

        // Tarjetas de prueba (como las de Stripe o PayPal Sandbox). Ninguna es real.
        // "aprobada" se muestra en la página para copiarla; "sin_fondos" sirve para probar un
        // pago rechazado. Cualquier otro dato se responde como "datos incorrectos".
        'tarjetas' => [
            'aprobada' => [
                'numero' => '4242424242424242',
                'vence'  => '12/30',
                'cvv'    => '123',
            ],
            'sin_fondos' => [
                'numero' => '4000000000009995',
                'vence'  => '12/30',
                'cvv'    => '123',
            ],
        ],
    ],

];
