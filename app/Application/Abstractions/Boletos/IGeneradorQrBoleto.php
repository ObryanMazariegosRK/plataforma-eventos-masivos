<?php

namespace App\Application\Abstractions\Boletos;

interface IGeneradorQrBoleto
{
    /**
     * Dibuja el QR de un boleto a partir de su código secreto. El QR lleva la URL
     * <app>/b#<código>: con la cámara del teléfono abre la página que decide qué hacer
     * según quién escanea (dueño → sus boletos, personal → registrar entrada, otro → tienda).
     */
    public function generarSvg(string $contenido): string;
}
