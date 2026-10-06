<?php
namespace App\Domain\Entities\Reembolsos;

use App\Domain\Enums\Reembolsos\EstadoReembolso;
use DomainException;

class Reembolso
{
    public function __construct(
        private ?int $id,
        private int $pagoId,
        private float $monto,
        private EstadoReembolso $estado,
        private ?string $motivo = null,
        private ?string $referenciaExterna = null,
    ) {
        $this->validar();
    }

    private function validar(): void
    {
        if ($this->monto <= 0) {
            throw new DomainException('El monto del reembolso debe ser mayor a cero.');
        }
    }

    // Comportamiento
    public function aprobar(): void
    {
        if ($this->estado !== EstadoReembolso::SOLICITADO) {
            throw new DomainException('Solo se puede aprobar un reembolso solicitado.');
        }
        $this->estado = EstadoReembolso::APROBADO;
    }

    public function rechazar(): void
    {
        if ($this->estado !== EstadoReembolso::SOLICITADO) {
            throw new DomainException('Solo se puede rechazar un reembolso solicitado.');
        }
        $this->estado = EstadoReembolso::RECHAZADO;
    }

    public function marcarProcesado(string $referenciaExterna): void
    {
        if ($this->estado !== EstadoReembolso::APROBADO) {
            throw new DomainException('Solo se procesa un reembolso aprobado.');
        }
        $this->estado = EstadoReembolso::PROCESADO;
        $this->referenciaExterna = $referenciaExterna;
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getPagoId(): int { return $this->pagoId; }
    public function getMonto(): float { return $this->monto; }
    public function getEstado(): EstadoReembolso { return $this->estado; }
    public function getMotivo(): ?string { return $this->motivo; }
    public function getReferenciaExterna(): ?string { return $this->referenciaExterna; }
}