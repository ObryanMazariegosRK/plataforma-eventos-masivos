<?php
namespace App\Application\Abstractions\Reservas;

interface IConsultarReservaUseCase
{
    // Evento y lugares de una reserva del usuario (p. ej. para "¡Pago aprobado!")
    public function execute(int $reservaId, int $usuarioId): array;
}
