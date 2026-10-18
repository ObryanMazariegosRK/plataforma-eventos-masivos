<?php
namespace App\Application\DTOs\Pagos;

// DTO de SALIDA de IniciarPago.
class PagoIniciadoDTO
{
    public function __construct(
        public readonly int $pagoId,
        public readonly string $urlAprobacion,
    ) {}
}
