<?php
namespace App\Domain\Enums\Boletos;

enum EstadoBoleto: string
{
    case EMITIDO = 'emitido';   // válido, aún no se usa
    case USADO   = 'usado';     // ya se escaneó en la entrada
    case ANULADO = 'anulado';   // invalidado (reembolso, cancelación)
}