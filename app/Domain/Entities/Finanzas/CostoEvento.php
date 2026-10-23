<?php

namespace App\Domain\Entities\Finanzas;

use App\Domain\Enums\Finanzas\CategoriaCosto;
use DomainException;

// Un gasto de producción de un evento: "Alquiler del teatro · Recinto · Q8,000"
class CostoEvento
{
    public const MONTO_MAXIMO = 9999999999.99;

    public function __construct(
        private ?int $id,
        private int $eventoId,
        private CategoriaCosto $categoria,
        private string $concepto,
        private float $monto,
    ) {
        $this->concepto = trim($this->concepto);
        $this->validar();
    }

    private function validar(): void
    {
        if ($this->concepto === '' || mb_strlen($this->concepto) > 150) {
            throw new DomainException('Cada costo necesita un concepto de hasta 150 caracteres.');
        }

        if ($this->monto <= 0 || $this->monto > self::MONTO_MAXIMO) {
            throw new DomainException("El costo «{$this->concepto}» debe ser mayor que Q0.");
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEventoId(): int
    {
        return $this->eventoId;
    }

    public function getCategoria(): CategoriaCosto
    {
        return $this->categoria;
    }

    public function getConcepto(): string
    {
        return $this->concepto;
    }

    public function getMonto(): float
    {
        return $this->monto;
    }
}
