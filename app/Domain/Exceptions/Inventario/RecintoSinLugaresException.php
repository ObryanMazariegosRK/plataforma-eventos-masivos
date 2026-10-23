<?php
namespace App\Domain\Exceptions\Inventario;

use App\Domain\Exceptions\ReglaDeNegocioException;

class RecintoSinLugaresException extends ReglaDeNegocioException
{
    public function __construct(string $mensaje = 'El recinto del evento no tiene localidades ni asientos configurados.')
    {
        parent::__construct($mensaje);
    }
}
