<?php
namespace App\Domain\Exceptions\Pagos;

use App\Domain\Exceptions\ReglaDeNegocioException;

class ReservaYaPagadaException extends ReglaDeNegocioException
{
    public function __construct(string $mensaje = 'Esta reserva ya fue pagada.')
    {
        parent::__construct($mensaje);
    }
}
