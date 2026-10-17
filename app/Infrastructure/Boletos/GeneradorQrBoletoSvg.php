<?php

namespace App\Infrastructure\Boletos;

use App\Application\Abstractions\Boletos\IGeneradorQrBoleto;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;

class GeneradorQrBoletoSvg implements IGeneradorQrBoleto
{
    public function generarSvg(string $contenido): string
    {
        $qrCode = new QrCode(
            data: $contenido,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 300,
            margin: 10
        );

        $writer = new SvgWriter();

        $resultado = $writer->write($qrCode);

        return $resultado->getString();
    }
}
