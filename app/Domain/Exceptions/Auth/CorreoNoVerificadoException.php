<?php
namespace App\Domain\Exceptions\Auth;

use App\Domain\Exceptions\ReglaDeNegocioException;

class CorreoNoVerificadoException extends ReglaDeNegocioException
{
    public function __construct(string $mensaje = 'Debes verificar tu correo electrónico antes de iniciar sesión.')
    {
        parent::__construct($mensaje);
    }
}
