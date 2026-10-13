<?php

namespace App\Application\Abstractions\Boletos;

use App\Domain\Entities\Boletos\Boleto;

interface IEmitirBoletosUseCase
{
    /**
     * Emite los boletos correspondientes
     * a una reserva con pago aprobado.
     *
     * @return Boleto[]
     */
    public function execute(int $pagoId): array;
}
