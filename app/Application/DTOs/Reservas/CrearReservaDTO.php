<?php
namespace App\Application\DTOs\Reservas;

class CrearReservaDTO
{
    public function __construct(
        public readonly int $usuarioId,
        public readonly int $eventoId,
        public readonly float $total,
        public readonly int $ttlSegundos,
    ) {}
}
