<?php
namespace App\Domain\Exceptions\Auth;

use App\Domain\Exceptions\ReglaDeNegocioException;

class CorreoYaRegistradoException extends ReglaDeNegocioException
{
    public function __construct(string $mensaje = 'El correo ya está registrado.')
    {
        parent::__construct($mensaje);
    }
}
