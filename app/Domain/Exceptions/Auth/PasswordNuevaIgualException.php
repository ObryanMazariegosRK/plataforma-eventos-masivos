<?php
namespace App\Domain\Exceptions\Auth;

use App\Domain\Exceptions\ReglaDeNegocioException;

class PasswordNuevaIgualException extends ReglaDeNegocioException
{
    public function __construct(string $mensaje = 'La nueva contraseña debe ser distinta de la actual.')
    {
        parent::__construct($mensaje);
    }
}
