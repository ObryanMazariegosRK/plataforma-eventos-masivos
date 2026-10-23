<?php

namespace App\Application\DTOs\Finanzas;

class GuardarCostosEventoDTO
{
    public function __construct(
        public readonly int $eventoId,
        public readonly int $usuarioId,
        /** @var list<array{categoria: string, concepto: string, monto: float|string}> */
        public readonly array $costos,
        public readonly int $maximo = 30,
    ) {}
}
