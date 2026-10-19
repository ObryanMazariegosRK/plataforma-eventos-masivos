<?php
namespace App\Domain\Exceptions\Pagos;

use App\Domain\Exceptions\ReglaDeNegocioException;

class PagoRechazadoException extends ReglaDeNegocioException
{
    public function __construct(string $mensaje = 'El pago fue rechazado.')
    {
        parent::__construct($mensaje);
    }
}
