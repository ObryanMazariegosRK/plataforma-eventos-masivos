<?php
namespace App\Domain\Enums\Transacciones;

enum TipoTransaccion: string
{
    case VENTA     = 'venta';       // entra dinero
    case REEMBOLSO = 'reembolso';   // sale dinero
}