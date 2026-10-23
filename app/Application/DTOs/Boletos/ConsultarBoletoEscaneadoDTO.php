<?php

namespace App\Application\DTOs\Boletos;

use DateTimeImmutable;

final readonly class ConsultarBoletoEscaneadoDTO
{
    public function __construct(
        public string $codigo,          // el código o la URL completa del QR (<app>/b#<código>)
        public int $usuarioId,
        public bool $esPersonal,        // admin u operador: puede registrar entradas
        public DateTimeImmutable $ahora,
        public string $zonaHoraria,     // para saber si la función es hoy
    ) {}
}
