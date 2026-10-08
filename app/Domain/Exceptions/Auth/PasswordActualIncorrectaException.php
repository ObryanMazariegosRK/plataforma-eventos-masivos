<?php
namespace App\Domain\Exceptions\Auth;

use App\Domain\Exceptions\ReglaDeNegocioException;

class PasswordActualIncorrectaException extends ReglaDeNegocioException
{
    public function __construct(string $mensaje = 'La contraseña actual es incorrecta.')
    {
        parent::__construct($mensaje);
    }
}
