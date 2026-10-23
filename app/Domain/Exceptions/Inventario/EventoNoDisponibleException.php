<?php
namespace App\Domain\Exceptions\Inventario;

use App\Domain\Exceptions\ReglaDeNegocioException;

class EventoNoDisponibleException extends ReglaDeNegocioException
{
    public function __construct(string $mensaje = 'El evento no existe o no está a la venta.')
    {
        parent::__construct($mensaje);
    }
}
