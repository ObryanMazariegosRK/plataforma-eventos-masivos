<?php
namespace App\Domain\Enums\Recintos;

enum EstadoEvento: string
{
    case BORRADOR   = 'borrador';     // se está armando, no visible al público
    case PUBLICADO  = 'publicado';    // a la venta
    case AGOTADO    = 'agotado';      // sin disponibilidad
    case FINALIZADO = 'finalizado';   // ya ocurrió
    case CANCELADO  = 'cancelado';    // se canceló
}