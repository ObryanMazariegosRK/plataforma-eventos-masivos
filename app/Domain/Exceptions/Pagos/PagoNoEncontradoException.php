<?php
namespace App\Domain\Exceptions\Pagos;

use App\Domain\Exceptions\ReglaDeNegocioException;

class PagoNoEncontradoException extends ReglaDeNegocioException
{
    public function __construct(string $mensaje = 'Pago no encontrado.')
    {
        parent::__construct($mensaje);
    }
}
