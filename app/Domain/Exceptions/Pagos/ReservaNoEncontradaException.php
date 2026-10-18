<?php
namespace App\Domain\Exceptions\Pagos;

use App\Domain\Exceptions\ReglaDeNegocioException;

class ReservaNoEncontradaException extends ReglaDeNegocioException
{
    public function __construct(string $mensaje = 'Reserva no encontrada.')
    {
        parent::__construct($mensaje);
    }
}
