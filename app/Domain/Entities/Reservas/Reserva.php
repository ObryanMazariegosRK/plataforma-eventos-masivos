<?php
namespace App\Domain\Entities\Reservas;

use App\Domain\Enums\Reservas\EstadoReserva;
use DateTimeImmutable;
use DomainException;

class Reserva
{
    public function __construct(
        private ?int $id,
        private int $usuarioId,
        private int $eventoId,
        private EstadoReserva $estado,
        private DateTimeImmutable $expiraEn,
        private float $total,
    ) {
        $this->validar();
    }

    private function validar(): void
    {
        if ($this->total < 0) {
            throw new DomainException('El total de la reserva no puede ser negativo.');
        }
    }

    // Comportamiento
    public function estaActiva(): bool
    {
        return $this->estado === EstadoReserva::ACTIVA;
    }

    public function haVencido(DateTimeImmutable $ahora): bool
    {
        return $this->estado === EstadoReserva::ACTIVA && $this->expiraEn < $ahora;
    }

    public function confirmar(): void
    {
        if ($this->estado !== EstadoReserva::ACTIVA) {
            throw new DomainException('Solo se puede confirmar una reserva activa.');
        }
        $this->estado = EstadoReserva::CONFIRMADA;
    }

    public function cancelar(): void
    {
        if ($this->estado === EstadoReserva::CONFIRMADA) {
            throw new DomainException('No se puede cancelar una reserva ya confirmada.');
        }
        $this->estado = EstadoReserva::CANCELADA;
    }

    public function vencer(): void
    {
        $this->estado = EstadoReserva::VENCIDA;
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getUsuarioId(): int { return $this->usuarioId; }
    public function getEventoId(): int { return $this->eventoId; }
    public function getEstado(): EstadoReserva { return $this->estado; }
    public function getExpiraEn(): DateTimeImmutable { return $this->expiraEn; }
    public function getTotal(): float { return $this->total; }
}