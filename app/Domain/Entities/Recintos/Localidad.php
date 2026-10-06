<?php
namespace App\Domain\Entities\Recintos;

use App\Domain\Enums\Recintos\TipoLocalidad;
use DomainException;

class Localidad
{
    public function __construct(
        private ?int $id,
        private int $recintoId,          // ← la relación: a qué recinto pertenece
        private string $nombre,
        private TipoLocalidad $tipo,
        private int $capacidad,
        private ?string $color = null,   // para pintar la zona en el mapa
        private ?string $area = null,    // ruta/polígono SVG de la zona
    ) {
        $this->validar();
    }

    private function validar(): void
    {
        if (trim($this->nombre) === '') {
            throw new DomainException('El nombre de la localidad no puede estar vacío.');
        }
        if ($this->capacidad <= 0) {
            throw new DomainException('La capacidad debe ser mayor a cero.');
        }
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getRecintoId(): int { return $this->recintoId; }
    public function getNombre(): string { return $this->nombre; }
    public function getTipo(): TipoLocalidad { return $this->tipo; }
    public function getCapacidad(): int { return $this->capacidad; }
    public function getColor(): ?string { return $this->color; }
    public function getArea(): ?string { return $this->area; }

    // Comportamiento
    public function esNumerada(): bool
    {
        return $this->tipo === TipoLocalidad::NUMERADA;
    }
}