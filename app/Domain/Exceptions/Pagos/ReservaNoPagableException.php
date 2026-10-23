<?php
namespace App\Domain\Exceptions\Pagos;

use App\Domain\Exceptions\ReglaDeNegocioException;

class ReservaNoPagableException extends ReglaDeNegocioException
{
    public function __construct(string $mensaje = 'La reserva ya no se puede pagar (venció, se canceló o ya fue pagada).')
    {
        parent::__construct($mensaje);
    }
}
