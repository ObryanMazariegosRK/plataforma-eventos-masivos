<?php
namespace App\Application\DTOs\Pagos;

class ConfirmarPagoDTO
{
    public function __construct(
        public readonly int $usuarioId,
        public readonly int $pagoId,
    ) {}
}
