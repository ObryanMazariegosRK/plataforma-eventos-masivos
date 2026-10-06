<?php
namespace App\Domain\Entities\Recintos;

use DomainException;

class Precio
{
    public function __construct(
        private ?int $id,
        private int $eventoId,       // a qué evento
        private int $localidadId,    // para qué localidad
        private float $precio,
    ) {
        $this->validar();
    }

    private function validar(): void
    {
        if ($this->precio < 0) {
            throw new DomainException('El precio no puede ser negativo.');
        }
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getEventoId(): int { return $this->eventoId; }
    public function getLocalidadId(): int { return $this->localidadId; }
    public function getPrecio(): float { return $this->precio; }
}