<?php
namespace App\Domain\Entities\Transacciones;

use App\Domain\Enums\Transacciones\TipoTransaccion;
use DomainException;

class Transaccion
{
    public function __construct(
        private ?int $id,
        private TipoTransaccion $tipo,
        private float $monto,
        private int $usuarioId,
        private ?int $reservaId = null,
        private ?int $pagoId = null,
        private ?string $referencia = null,
        private ?string $descripcion = null,
    ) {
        $this->validar();
    }

    private function validar(): void
    {
        if ($this->monto <= 0) {
            throw new DomainException('El monto de la transacción debe ser mayor a cero.');
        }
    }

    // Solo getters — NO hay métodos que cambien estado (es inmutable)
    public function getId(): ?int { return $this->id; }
    public function getTipo(): TipoTransaccion { return $this->tipo; }
    public function getMonto(): float { return $this->monto; }
    public function getUsuarioId(): int { return $this->usuarioId; }
    public function getReservaId(): ?int { return $this->reservaId; }
    public function getPagoId(): ?int { return $this->pagoId; }
    public function getReferencia(): ?string { return $this->referencia; }
    public function getDescripcion(): ?string { return $this->descripcion; }

    public function esVenta(): bool { return $this->tipo === TipoTransaccion::VENTA; }
    public function esReembolso(): bool { return $this->tipo === TipoTransaccion::REEMBOLSO; }
}