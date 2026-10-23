<?php
namespace App\Domain\Exceptions\Auth;

use App\Domain\Exceptions\ReglaDeNegocioException;

// Una cuenta del personal (admin u operador) intentó entrar por la tienda o con Google:
// el personal solo entra por su propio acceso (/admin/login), con correo y contraseña.
class AccesoDePersonalException extends ReglaDeNegocioException
{
    public function __construct(string $mensaje = 'Las cuentas del personal inician sesión en el acceso del personal.')
    {
        parent::__construct($mensaje);
    }
}
