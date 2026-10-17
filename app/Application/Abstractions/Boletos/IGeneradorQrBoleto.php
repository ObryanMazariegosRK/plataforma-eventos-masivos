<?php

namespace App\Application\Abstractions\Boletos;

interface IGeneradorQrBoleto
{
    public function generarSvg(string $contenido): string;
}
