<?php
namespace App\Domain\Exceptions\Auth;

use App\Domain\Exceptions\ReglaDeNegocioException;

class UsuarioBloqueadoException extends ReglaDeNegocioException
{
    public function __construct(string $mensaje = 'Tu cuenta está bloqueada. Contacta al administrador.')
    {
        parent::__construct($mensaje);
    }
}
