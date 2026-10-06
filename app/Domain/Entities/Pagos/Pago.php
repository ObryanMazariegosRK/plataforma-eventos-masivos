<?php
namespace App\Domain\Entities\Pagos;

use App\Domain\Enums\Pagos\EstadoPago;
use DomainException;

class Pago
{
    public function __construct(
        private ?int $id,
        private int $reservaId,
        private float $monto,
        private EstadoPago $estado,
        private int $intento = 1,
        private ?int $pasarelaId = null,
        private ?string $referenciaExterna = null,
    ) {
        $this->validar();
    }

    private function validar(): void
    {
        if ($this->monto <= 0) {
            throw new DomainException('El monto del pago debe ser mayor a cero.');
        }
        if ($this->intento < 1) {
            throw new DomainException('El número de intento debe ser al menos 1.');
        }
    }

    // Comportamiento
    public function fueAprobado(): bool
    {
        return $this->estado === EstadoPago::APROBADO;
    }

    public function aprobar(string $referenciaExterna): void
    {
        if ($this->estado !== EstadoPago::PENDIENTE) {
            throw new DomainException('Solo se puede aprobar un pago pendiente.');
        }
        $this->estado = EstadoPago::APROBADO;
        $this->referenciaExterna = $referenciaExterna;
    }

    public function rechazar(): void
    {
        if ($this->estado !== EstadoPago::PENDIENTE) {
            throw new DomainException('Solo se puede rechazar un pago pendiente.');
        }
        $this->estado = EstadoPago::RECHAZADO;
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getReservaId(): int { return $this->reservaId; }
    public function getMonto(): float { return $this->monto; }
    public function getEstado(): EstadoPago { return $this->estado; }
    public function getIntento(): int { return $this->intento; }
    public function getPasarelaId(): ?int { return $this->pasarelaId; }
    public function getReferenciaExterna(): ?string { return $this->referenciaExterna; }
}