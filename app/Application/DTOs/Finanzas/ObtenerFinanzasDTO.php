<?php

namespace App\Application\DTOs\Finanzas;

use DateTimeImmutable;

class ObtenerFinanzasDTO
{
    public function __construct(
        public readonly DateTimeImmutable $ahora,
        public readonly string $zonaHoraria,       // días y meses de esta zona; la base guarda UTC
        public readonly int $dias = 30,
        public readonly float $comisionPasarela = 0.035,
    ) {}
}
