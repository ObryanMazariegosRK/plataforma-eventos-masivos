<?php

return [

    // Minutos de validez del código de verificación enviado por correo.
    'codigo_verificacion_minutos' => (int) env('AUTH_CODIGO_MINUTOS', 15),

    // Minutos de validez del código para restablecer la contraseña.
    'codigo_recuperacion_minutos' => (int) env('AUTH_CODIGO_RECUPERACION_MINUTOS', 15),

    // Vida máxima de una sesión (cualquier rol), contada desde que se inicia: pasado ese tiempo
    // hay que volver a entrar aunque la pestaña siga abierta. Además, la sesión termina al cerrar
    // sesión o al cerrar el navegador (public/js/sesion.js). No hay "recordarme".
    'token_horas' => (int) env('AUTH_TOKEN_HORAS', 12),

];
