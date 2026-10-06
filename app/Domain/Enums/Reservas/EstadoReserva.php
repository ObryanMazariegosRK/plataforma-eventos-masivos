<?php
namespace App\Domain\Enums\Reservas;

enum EstadoReserva: string
{
    case ACTIVA     = 'activa';      // retención temporal (TTL corriendo)
    case CONFIRMADA = 'confirmada';  // ya pagada
    case VENCIDA    = 'vencida';     // expiró el TTL
    case CANCELADA  = 'cancelada';
}