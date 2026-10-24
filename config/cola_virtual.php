<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Configuración de Cola Virtual
    |--------------------------------------------------------------------------
    |
    | Define cuántos usuarios pueden comprar simultáneamente
    | y cuánto tiempo permanece habilitado cada turno.
    |
    */

    // Cantidad máxima de turnos habilitados simultáneamente (recintos de ≤ 100 lugares y
    // hasta 5 boletos por persona: 10 a la vez evita que alguien entre a un mapa casi vacío).
    'max_turnos_simultaneos' => 10,

    // Tiempo disponible para realizar una reserva.
    'ventana_compra_minutos' => 5,

    
    // La fila avanza al entrar, al consultar el turno (cada pocos segundos desde la sala de
    // espera, como mucho una vez por segundo por evento) y, como respaldo, cada minuto.
    'procesamiento_automatico_habilitado' => true,

    // Avisar cuando queden como máximo dos turnos activos delante.
    'aviso_proximo_personas' => 2,
];
