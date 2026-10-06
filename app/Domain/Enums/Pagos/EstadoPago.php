<?php
namespace App\Domain\Enums\Pagos;

enum EstadoPago: string
{
    case PENDIENTE = 'pendiente';
    case APROBADO  = 'aprobado';
    case RECHAZADO = 'rechazado';
}