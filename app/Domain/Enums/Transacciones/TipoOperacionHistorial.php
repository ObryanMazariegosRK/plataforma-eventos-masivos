<?php

namespace App\Domain\Enums\Transacciones;

enum TipoOperacionHistorial: string
{
    case RESERVA_CREADA = 'reserva_creada';
    case RESERVA_LIBERADA = 'reserva_liberada';
    case RESERVA_CANCELADA = 'reserva_cancelada';
    case VENTA_CONFIRMADA = 'venta_confirmada';
    case REEMBOLSO_CONFIRMADO = 'reembolso_confirmado';
}
