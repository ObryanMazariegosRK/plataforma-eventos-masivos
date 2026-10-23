<?php

namespace App\Application\UseCases\Boletos;

use App\Application\Abstractions\Boletos\IObtenerQrBoletoUseCase;
use App\Application\Abstractions\Boletos\IGeneradorQrBoleto;
use App\Domain\Abstractions\Boletos\IBoletoRepository;
use DomainException;

class ObtenerQrBoletoUseCase implements IObtenerQrBoletoUseCase
{
    public function __construct(
        private IBoletoRepository $boletos,
        private IGeneradorQrBoleto $generadorQr
    ) {}

    public function execute(
        int $boletoId,
        int $usuarioId
    ): string {

        // 1. Buscar el boleto mediante su ID.
        $boleto = $this->boletos->buscarPorId($boletoId);

        // 2. Verificar que exista y pertenezca al usuario.
        if (
            $boleto === null ||
            $boleto->getUsuarioId() !== $usuarioId
        ) {
            throw new DomainException(
                'El boleto no está disponible.'
            );
        }

        // 3. Verificar que el boleto esté emitido.
        if (!$boleto->estaEmitido()) {
            throw new DomainException(
                'El boleto no está en estado emitido.'
            );
        }

        // 4. Obtener el identificador único del boleto.
        $codigoQr = $boleto->getCodigoQr();

        // 5. Generar y devolver el QR en formato SVG.
        return $this->generadorQr->generarSvg($codigoQr);
    }
}
