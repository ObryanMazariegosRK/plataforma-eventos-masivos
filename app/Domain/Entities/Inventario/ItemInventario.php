<?php
namespace App\Domain\Entities\Inventario;

use App\Domain\Enums\Inventario\EstadoAsiento;
use DateTimeImmutable;
use DomainException;

class ItemInventario
{
    public function __construct(
        private ?int $id,
        private int $eventoId,
        private int $localidadId,
        private ?int $asientoId,          // null = cupo de localidad general
        private EstadoAsiento $estado,
        private ?int $reservaId = null,
        private ?DateTimeImmutable $bloqueadoHasta = null,
    ) {
        $this->validar();
    }

    private function validar(): void
    {
        if ($this->estado === EstadoAsiento::BLOQUEADO
            && ($this->reservaId === null || $this->bloqueadoHasta === null)) {
            throw new DomainException('Un asiento bloqueado debe tener reserva y fecha de expiración.');
        }
    }

    // ---- La máquina de estados vive aquí (la entidad se protege) ----

    public function estaDisponible(): bool
    {
        return $this->estado === EstadoAsiento::DISPONIBLE;
    }

    public function bloqueoVencido(DateTimeImmutable $ahora): bool
    {
        return $this->estado === EstadoAsiento::BLOQUEADO
            && $this->bloqueadoHasta !== null
            && $this->bloqueadoHasta < $ahora;
    }

    public function bloquear(int $reservaId, DateTimeImmutable $hasta): void
    {
        if (! $this->estaDisponible()) {
            throw new DomainException('Solo se puede bloquear un asiento disponible.');
        }
        $this->estado = EstadoAsiento::BLOQUEADO;
        $this->reservaId = $reservaId;
        $this->bloqueadoHasta = $hasta;
    }

    public function liberar(): void
    {
        $this->estado = EstadoAsiento::DISPONIBLE;
        $this->reservaId = null;
        $this->bloqueadoHasta = null;
    }

    public function vender(): void
    {
        if ($this->estado !== EstadoAsiento::BLOQUEADO) {
            throw new DomainException('Solo se puede vender un asiento previamente bloqueado.');
        }
        $this->estado = EstadoAsiento::VENDIDO;
        $this->bloqueadoHasta = null;
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getEventoId(): int { return $this->eventoId; }
    public function getLocalidadId(): int { return $this->localidadId; }
    public function getAsientoId(): ?int { return $this->asientoId; }
    public function getEstado(): EstadoAsiento { return $this->estado; }
    public function getReservaId(): ?int { return $this->reservaId; }
    public function getBloqueadoHasta(): ?DateTimeImmutable { return $this->bloqueadoHasta; }
}