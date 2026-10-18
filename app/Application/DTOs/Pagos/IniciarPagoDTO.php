<?php
namespace App\Application\DTOs\Pagos;

class IniciarPagoDTO
{
    public function __construct(
        public readonly int $usuarioId,
        public readonly int $reservaId,
        public readonly string $codigoPasarela,
        public readonly string $moneda,
        public readonly string $urlRetorno,       // a dónde vuelve el navegador después de aprobar/rechazar
        public readonly string $urlCancelacion,   // a dónde vuelve si la persona cancela
    ) {}
}
