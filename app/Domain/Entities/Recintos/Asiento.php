<?php
namespace App\Domain\Entities\Recintos;

use DomainException;

class Asiento
{
    public function __construct(
        private ?int $id,
        private int $localidadId,        //a qué localidad pertenece
        private string $fila,
        private int $numero,
        private ?float $posX = null,     //coordenada X en el mapa
        private ?float $posY = null,     //coordenada Y en el mapa
    ) {
        $this->validar();
    }

    private function validar(): void
    {
        if (trim($this->fila) === '') {
            throw new DomainException('La fila del asiento no puede estar vacía.');
        }
        if ($this->numero <= 0) {
            throw new DomainException('El número de asiento debe ser mayor a cero.');
        }
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getLocalidadId(): int { return $this->localidadId; }
    public function getFila(): string { return $this->fila; }
    public function getNumero(): int { return $this->numero; }
    public function getPosX(): ?float { return $this->posX; }
    public function getPosY(): ?float { return $this->posY; }

    // Etiqueta derivada (no se guarda): "A-12"
    public function getEtiqueta(): string
    {
        return "{$this->fila}-{$this->numero}";
    }
}