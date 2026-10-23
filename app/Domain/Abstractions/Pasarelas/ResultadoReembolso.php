<?php

namespace App\Domain\Abstractions\Pasarelas;

/** Respuesta de la pasarela al devolver (total o parcialmente) un cobro ya capturado. */
class ResultadoReembolso
{
    private function __construct(
        public readonly bool $aprobado,
        public readonly ?string $referencia,   // id de la devolución (solo si se hizo)
        public readonly ?string $motivo,       // por qué no se devolvió (solo si falló)
    ) {}

    public static function aprobado(string $referencia): self
    {
        return new self(true, $referencia, null);
    }

    public static function rechazado(string $motivo): self
    {
        return new self(false, null, $motivo);
    }
}
