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
        // El alquiler del recinto y los artistas son obligatorios (no se arma un evento sin saberlos)
        public readonly bool $exigirBasicos = true,
    ) {}
}
