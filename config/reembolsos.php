<?php

return [

    // Habilitar la politica de reembolsos.
    'politica_habilitada' => true,

    // Reglas para cancelaciones voluntarias.
    // Las horas_minimas representan el tiempo
    // restante antes del inicio del evento.
    'tramos' => [

        // 7 dias o mas: devolucion completa.
        [
            'horas_minimas' => 168,
            'penalizacion_porcentaje' => 0,
        ],

        // Desde 72 horas: penalizacion del 20%.
        [
            'horas_minimas' => 72,
            'penalizacion_porcentaje' => 20,
        ],

        // Desde 24 horas: penalizacion del 50%.
        [
            'horas_minimas' => 24,
            'penalizacion_porcentaje' => 50,
        ],

    ],

    // Excepcion: evento cancelado por el organizador.
    // Se implementara en el flujo de cancelacion masiva.
    'reembolso_cancelacion_evento_porcentaje' => 100,

];
