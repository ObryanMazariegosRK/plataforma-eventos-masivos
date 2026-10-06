<?php
namespace App\Domain\Enums\Notificaciones;

enum EstadoNotificacion: string
{
    case PENDIENTE = 'pendiente';
    case ENVIADA   = 'enviada';
    case FALLIDA   = 'fallida';
}