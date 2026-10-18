<?php
namespace App\Domain\Abstractions\Pasarelas;

/** Respuesta de la pasarela al intentar cobrar una orden (paso 3). */
class ResultadoCaptura
{
    private function __construct(
        public readonly bool $aprobado,
        public readonly ?string $referencia,   // id del cobro (solo si se aprobó)
        public readonly ?string $motivo,       // por qué no se cobró (solo si se rechazó)
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
