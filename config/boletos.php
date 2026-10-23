<?php

return [

    // Dirección pública que va dentro del QR de los boletos (<url>/b#<código>), p. ej. la de ngrok
    // (https://xxxx.ngrok-free.app) para escanear con un teléfono. Vacía: se usa la dirección desde
    // la que se abrió la página de boletos.
    'url_publica' => env('BOLETOS_URL_PUBLICA'),

];
