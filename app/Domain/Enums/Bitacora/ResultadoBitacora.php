<?php

namespace App\Domain\Enums\Bitacora;

enum ResultadoBitacora: string
{
    case EXITO = 'exito';
    case FALLIDO = 'fallido';   // ej. contraseña incorrecta, pago rechazado, QR inválido
}
