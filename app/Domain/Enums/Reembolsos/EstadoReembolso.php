<?php
namespace App\Domain\Enums\Reembolsos;

enum EstadoReembolso: string
{
    case SOLICITADO = 'solicitado';
    case APROBADO   = 'aprobado';
    case RECHAZADO  = 'rechazado';
    case PROCESADO  = 'procesado';   // dinero ya devuelto
}