<?php
namespace App\Domain\Exceptions\Auth;

use App\Domain\Exceptions\ReglaDeNegocioException;

class CodigoVerificacionInvalidoException extends ReglaDeNegocioException
{
    public function __construct(string $mensaje = 'El código de verificación es incorrecto o ha expirado.')
    {
        parent::__construct($mensaje);
    }
}
