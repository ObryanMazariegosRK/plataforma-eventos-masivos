<?php
namespace App\Domain\Entities\Boletos;

use App\Domain\Enums\Boletos\EstadoBoleto;
use DateTimeImmutable;
use DomainException;

class Boleto
{
    public function __construct(
        private ?int $id,
        private int $reservaId,
        private int $inventarioId,   // el asiento/cupo vendido al que corresponde
        private int $usuarioId,
        private string $codigoQr,
        private EstadoBoleto $estado,
        private ?DateTimeImmutable $usadoEn = null,
    ) {
        $this->validar();
    }

    private function validar(): void
    {
        if (trim($this->codigoQr) === '') {
            throw new DomainException('El boleto debe tener un código QR.');
        }
    }

    // Comportamiento
    public function estaEmitido(): bool
    {
        return $this->estado === EstadoBoleto::EMITIDO;
    }

    public function usar(DateTimeImmutable $momento): void
    {
        if ($this->estado !== EstadoBoleto::EMITIDO) {
            throw new DomainException('Solo se puede usar un boleto emitido.');
        }
        $this->estado = EstadoBoleto::USADO;
        $this->usadoEn = $momento;
    }

    public function anular(): void
    {
        if ($this->estado === EstadoBoleto::USADO) {
            throw new DomainException('No se puede anular un boleto ya usado.');
        }
        $this->estado = EstadoBoleto::ANULADO;
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getReservaId(): int { return $this->reservaId; }
    public function getInventarioId(): int { return $this->inventarioId; }
    public function getUsuarioId(): int { return $this->usuarioId; }
    public function getCodigoQr(): string { return $this->codigoQr; }
    public function getEstado(): EstadoBoleto { return $this->estado; }
    public function getUsadoEn(): ?DateTimeImmutable { return $this->usadoEn; }
}