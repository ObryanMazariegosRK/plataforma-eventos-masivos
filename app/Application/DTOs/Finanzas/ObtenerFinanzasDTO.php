<?php

namespace App\Application\DTOs\Finanzas;

use DateTimeImmutable;

class ObtenerFinanzasDTO
{
    public function __construct(
        public readonly DateTimeImmutable $ahora,
        public readonly string $zonaHoraria,       // días de esta zona; la base guarda UTC
        public readonly ?string $desde = null,     // Y-m-d (incluido); sin él: los últimos días por defecto
        public readonly ?string $hasta = null,     // Y-m-d (incluido); sin él: hoy
        public readonly int $diasPorDefecto = 30,
        public readonly float $comisionPasarela = 0.035,
    ) {}
}
