<?php
namespace App\Domain\Exceptions\Auth;

use App\Domain\Exceptions\ReglaDeNegocioException;

class CorreoYaVerificadoException extends ReglaDeNegocioException
{
    public function __construct(string $mensaje = 'Este correo ya fue verificado.')
    {
        parent::__construct($mensaje);
    }
}
