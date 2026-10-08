<?php
namespace App\Domain\Exceptions\Auth;

use App\Domain\Exceptions\ReglaDeNegocioException;

class UsuarioNoEncontradoException extends ReglaDeNegocioException
{
    public function __construct(string $mensaje = 'Usuario no encontrado.')
    {
        parent::__construct($mensaje);
    }
}
