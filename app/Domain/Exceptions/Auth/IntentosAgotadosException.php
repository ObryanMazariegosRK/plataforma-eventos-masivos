<?php
namespace App\Domain\Exceptions\Auth;

use App\Domain\Exceptions\ReglaDeNegocioException;

class IntentosAgotadosException extends ReglaDeNegocioException
{
    public function __construct(string $mensaje = 'Demasiados intentos fallidos. Solicita un código nuevo.')
    {
        parent::__construct($mensaje);
    }
}
