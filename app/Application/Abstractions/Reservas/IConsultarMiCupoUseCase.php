<?php
namespace App\Application\Abstractions\Reservas;

use DateTimeImmutable;

interface IConsultarMiCupoUseCase
{
    // Cuántos boletos más puede apartar la persona en el evento: {maximo, usados, disponibles}
    public function execute(int $usuarioId, int $eventoId, int $maximo, DateTimeImmutable $ahora): array;
}
