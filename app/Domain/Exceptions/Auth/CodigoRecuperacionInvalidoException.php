<?php
namespace App\Domain\Exceptions\Auth;

use App\Domain\Exceptions\ReglaDeNegocioException;

class CodigoRecuperacionInvalidoException extends ReglaDeNegocioException
{
    public function __construct(string $mensaje = 'El código de recuperación es incorrecto o ha expirado.')
    {
        parent::__construct($mensaje);
    }
}
