<?php

namespace App\Application\Abstractions\Reembolsos;

use App\Domain\Entities\Reembolsos\Reembolso;

interface IProcesarReembolsoUseCase
{
    /** Devuelve el dinero de un reembolso APROBADO en la pasarela y lo deja PROCESADO. */
    public function execute(int $reembolsoId, int $procesadoPor): Reembolso;
}
