<?php

namespace App\Domain\Exceptions\Reembolsos;

use App\Domain\Exceptions\ReglaDeNegocioException;

/** La pasarela no hizo la devolución: el reembolso sigue aprobado y se puede reintentar. */
class ReembolsoNoProcesadoException extends ReglaDeNegocioException
{
    public function __construct(string $mensaje = 'La pasarela no pudo devolver el dinero. Inténtalo de nuevo más tarde.')
    {
        parent::__construct($mensaje);
    }
}
