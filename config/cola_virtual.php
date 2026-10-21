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

    // Cantidad máxima de turnos habilitados simultáneamente.
    'max_turnos_simultaneos' => 5,

    // Tiempo disponible para realizar una reserva.
    'ventana_compra_minutos' => 5,

    
    // Activar únicamente cuando el procesamiento
    // automático esté listo para utilizarse.
    'procesamiento_automatico_habilitado' => false,

    // Avisar cuando queden como máximo dos turnos activos delante.
    'aviso_proximo_personas' => 2,
];
