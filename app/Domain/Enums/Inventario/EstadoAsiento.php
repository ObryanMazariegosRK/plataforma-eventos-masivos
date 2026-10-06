<?php
namespace App\Domain\Enums\Inventario;

enum EstadoAsiento: string
{
    case DISPONIBLE = 'disponible';
    case BLOQUEADO  = 'bloqueado';   // retenido temporalmente (TTL corriendo)
    case VENDIDO    = 'vendido';
}