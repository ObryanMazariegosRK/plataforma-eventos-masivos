<?php
namespace App\Application\DTOs\Reservas;

class CrearReservaDTO
{
    public function __construct(
        public readonly int $usuarioId,
        public readonly int $eventoId,
        public readonly float $total,
        public readonly int $ttlSegundos,
        public readonly array $inventarioIds = [],   // asientos numerados elegidos uno por uno
        /** @var list<array{localidad_id: int, cantidad: int}> lugares de localidades generales, por cantidad */
        public readonly array $cupos = [],
        // boletos por persona en el evento (pagados + apartados vigentes + esta reserva)
        public readonly int $maxPorPersona = 5,

    ) {}
}
