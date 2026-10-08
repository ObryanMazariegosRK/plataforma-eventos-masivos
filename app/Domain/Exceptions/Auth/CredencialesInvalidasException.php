<?php
namespace App\Domain\Exceptions\Auth;

use App\Domain\Exceptions\ReglaDeNegocioException;

class CredencialesInvalidasException extends ReglaDeNegocioException
{
    public function __construct(string $mensaje = 'Las credenciales proporcionadas son incorrectas.')
    {
        parent::__construct($mensaje);
    }
}
