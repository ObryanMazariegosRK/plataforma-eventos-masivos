<?php

namespace App\Application\DTOs\Admin;

class GenerarReporteAdminDTO
{
    public function __construct(
        public readonly string $tipo,              // eventos | reservas | pagos | usuarios
        public readonly int $limite,
        public readonly string $zonaHoraria,       // los días del filtro y las fechas del informe
        public readonly ?string $desde = null,     // Y-m-d (incluido)
        public readonly ?string $hasta = null,     // Y-m-d (incluido)
        public readonly ?string $generadoPor = null,
    ) {}
}
