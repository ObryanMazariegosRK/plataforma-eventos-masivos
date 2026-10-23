<?php

namespace App\Application\Abstractions\Reembolsos;

use DateTimeImmutable;

interface IConsultarReembolsoDeCompraUseCase
{
    /**
     * Lo que vería el cliente antes de pedir el reembolso de una compra suya.
     *
     * @return array{reserva_id: int, evento: string, monto_pagado: float, puede_solicitar: bool,
     *               motivo_no: ?string, calculo: ?array, reembolso: ?array}
     */
    public function execute(int $reservaId, int $usuarioId, DateTimeImmutable $ahora): array;
}
