<?php
namespace App\Domain\Enums\ColaVirtual;

enum EstadoTurno: string
{
    case ESPERANDO  = 'esperando';   // en la fila
    case HABILITADO = 'habilitado';  // su turno: puede comprar (ventana con tiempo)
    case EXPIRADO   = 'expirado';    // no compró a tiempo
    case ATENDIDO   = 'atendido';    // ya pasó al flujo de compra
}