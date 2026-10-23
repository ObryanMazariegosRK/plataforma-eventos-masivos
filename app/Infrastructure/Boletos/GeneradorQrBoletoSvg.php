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
            data: $this->enlace($contenido),
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 300,
            margin: 10
        );

        $writer = new SvgWriter();

        $resultado = $writer->write($qrCode);

        return $resultado->getString();
    }

    /** Lo que lleva el QR: <dirección>/b#<código>. */
    public function enlace(string $codigo): string
    {
        // El código va en el fragmento (#): el navegador no lo envía al servidor,
        // así que no queda en los registros de acceso de nginx.
        // La dirección pública (ngrok, dominio real) manda sobre la de la petición.
        $publica = config('boletos.url_publica');
        $entrada = $publica ? rtrim($publica, '/').'/b' : route('boletos.entrada');

        return $entrada.'#'.$codigo;
    }
}
