<?php

namespace App\Domain\Exceptions\Admin;

use App\Domain\Exceptions\ReglaDeNegocioException;

class ReporteDemasiadoGrandeException extends ReglaDeNegocioException
{
    public function __construct(int $limite)
    {
        parent::__construct("El informe supera {$limite} registros. Selecciona un período más corto.");
    }
}
