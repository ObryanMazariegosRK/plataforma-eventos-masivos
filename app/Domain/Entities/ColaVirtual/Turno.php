<?php
namespace App\Domain\Entities\ColaVirtual;

use App\Domain\Enums\ColaVirtual\EstadoTurno;
use DateTimeImmutable;
use DomainException;

class Turno
{
    public function __construct(
        private ?int $id,
        private int $eventoId,
        private int $usuarioId,
        private int $posicion,
        private string $token,
        private EstadoTurno $estado,
        private ?DateTimeImmutable $habilitadoHasta = null,
    ) {
        $this->validar();
    }

    private function validar(): void
    {
        if ($this->posicion <= 0) {
            throw new DomainException('La posición en la cola debe ser mayor a cero.');
        }
        if (trim($this->token) === '') {
            throw new DomainException('El turno debe tener un token.');
        }
    }

    // Comportamiento
    public function estaEsperando(): bool
    {
        return $this->estado === EstadoTurno::ESPERANDO;
    }

    public function habilitar(DateTimeImmutable $hasta): void
    {
        if ($this->estado !== EstadoTurno::ESPERANDO) {
            throw new DomainException('Solo se puede habilitar un turno en espera.');
        }
        $this->estado = EstadoTurno::HABILITADO;
        $this->habilitadoHasta = $hasta;
    }

    public function ventanaVencida(DateTimeImmutable $ahora): bool
    {
        return $this->estado === EstadoTurno::HABILITADO
            && $this->habilitadoHasta !== null
            && $this->habilitadoHasta < $ahora;
    }

    public function expirar(): void
    {
        $this->estado = EstadoTurno::EXPIRADO;
    }

    public function marcarAtendido(): void
    {
        if ($this->estado !== EstadoTurno::HABILITADO) {
            throw new DomainException('Solo se marca atendido un turno habilitado.');
        }
        $this->estado = EstadoTurno::ATENDIDO;
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getEventoId(): int { return $this->eventoId; }
    public function getUsuarioId(): int { return $this->usuarioId; }
    public function getPosicion(): int { return $this->posicion; }
    public function getToken(): string { return $this->token; }
    public function getEstado(): EstadoTurno { return $this->estado; }
    public function getHabilitadoHasta(): ?DateTimeImmutable { return $this->habilitadoHasta; }
}