<?php
namespace App\Domain\Entities\Recintos;

use App\Domain\Enums\Recintos\EstadoRecinto;
use DomainException;

class Recinto
{
    public function __construct(
        private ?int $id,
        private string $nombre,
        private string $direccion,
        private string $ciudad,
        private EstadoRecinto $estado,
        private ?string $descripcion = null,
    ) {
        $this->validar();
    }

    private function validar(): void
    {
        if (trim($this->nombre) === '') {
            throw new DomainException('El nombre del recinto no puede estar vacío.');
        }
        if (trim($this->direccion) === '') {
            throw new DomainException('La dirección del recinto no puede estar vacía.');
        }
        if (trim($this->ciudad) === '') {
            throw new DomainException('La ciudad del recinto no puede estar vacía.');
        }
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getNombre(): string { return $this->nombre; }
    public function getDireccion(): string { return $this->direccion; }
    public function getCiudad(): string { return $this->ciudad; }
    public function getEstado(): EstadoRecinto { return $this->estado; }
    public function getDescripcion(): ?string { return $this->descripcion; }

    // Comportamiento de negocio
    public function estaActivo(): bool
    {
        return $this->estado === EstadoRecinto::ACTIVO;
    }
}