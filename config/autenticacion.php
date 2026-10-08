<?php

return [

    // Minutos de validez del código de verificación enviado por correo.
    'codigo_verificacion_minutos' => (int) env('AUTH_CODIGO_MINUTOS', 15),

    // Minutos de validez del código para restablecer la contraseña.
    'codigo_recuperacion_minutos' => (int) env('AUTH_CODIGO_RECUPERACION_MINUTOS', 15),

    // Vida del token de acceso: normal vs. "recordarme".
    'token_horas'         => (int) env('AUTH_TOKEN_HORAS', 24),
    'token_recordar_dias' => (int) env('AUTH_TOKEN_RECORDAR_DIAS', 30),

];
