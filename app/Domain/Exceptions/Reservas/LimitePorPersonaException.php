<?php
namespace App\Domain\Exceptions\Reservas;

use App\Domain\Exceptions\ReglaDeNegocioException;

// Máximo de boletos por persona en un mismo evento (pagados + apartados vigentes)
class LimitePorPersonaException extends ReglaDeNegocioException
{
    public function __construct(int $maximo, int $yaTiene)
    {
        $quedan = max(0, $maximo - $yaTiene);

        parent::__construct($quedan === 0
            ? "Ya tienes {$maximo} boletos para esta función, el máximo por persona."
            : "Puedes tener hasta {$maximo} boletos por persona para esta función. Ya tienes {$yaTiene}, así que puedes elegir {$quedan} más.");
    }
}
