<?php

namespace App\Domain\Exceptions\Finanzas;

use App\Domain\Exceptions\ReglaDeNegocioException;

// El evento no existe (o fue eliminado): no se le pueden registrar costos
class EventoSinCostosEditablesException extends ReglaDeNegocioException
{
    public function __construct(int $eventoId)
    {
        parent::__construct("No existe el evento #{$eventoId}.");
    }
}
