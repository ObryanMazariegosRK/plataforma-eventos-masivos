<?php
namespace App\Domain\Exceptions\Pagos;

use App\Domain\Exceptions\ReglaDeNegocioException;

class PasarelaNoDisponibleException extends ReglaDeNegocioException
{
    public function __construct(string $mensaje = 'La pasarela de pago no está disponible.')
    {
        parent::__construct($mensaje);
    }
}
