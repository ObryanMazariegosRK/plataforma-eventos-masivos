<?php

namespace App\Application\Abstractions\Boletos;

interface IObtenerQrBoletoUseCase
{
    public function execute(
        int $boletoId,
        int $usuarioId
    ): string;
}
