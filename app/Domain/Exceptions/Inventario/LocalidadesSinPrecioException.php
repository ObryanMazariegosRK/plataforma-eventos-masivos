<?php
namespace App\Domain\Exceptions\Inventario;

use App\Domain\Exceptions\ReglaDeNegocioException;

class LocalidadesSinPrecioException extends ReglaDeNegocioException
{
    public function __construct(string $mensaje = 'Todas las localidades deben tener precio antes de publicar el evento.')
    {
        parent::__construct($mensaje);
    }
}
