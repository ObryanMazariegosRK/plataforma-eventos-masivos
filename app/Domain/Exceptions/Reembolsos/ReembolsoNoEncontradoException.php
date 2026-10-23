<?php

namespace App\Domain\Exceptions\Reembolsos;

use App\Domain\Exceptions\ReglaDeNegocioException;

class ReembolsoNoEncontradoException extends ReglaDeNegocioException
{
    public function __construct(string $mensaje = 'La solicitud de reembolso no existe.')
    {
        parent::__construct($mensaje);
    }
}
