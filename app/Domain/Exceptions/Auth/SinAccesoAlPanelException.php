<?php
namespace App\Domain\Exceptions\Auth;

use App\Domain\Exceptions\ReglaDeNegocioException;

// Una cuenta de cliente intentó entrar por el acceso del personal
class SinAccesoAlPanelException extends ReglaDeNegocioException
{
    public function __construct(string $mensaje = 'Esta cuenta no tiene acceso al panel. Inicia sesión en la tienda.')
    {
        parent::__construct($mensaje);
    }
}
