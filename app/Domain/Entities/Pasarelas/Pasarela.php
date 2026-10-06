<?php
namespace App\Domain\Entities\Pasarelas;

use DomainException;

class Pasarela
{
    public function __construct(
        private ?int $id,
        private string $nombre,
        private string $codigo,     // identificador que usa el adaptador: "paypal", "visanet"...
        private bool $activa = true,
    ) {
        $this->validar();
    }

    private function validar(): void
    {
        if (trim($this->nombre) === '') {
            throw new DomainException('El nombre de la pasarela no puede estar vacío.');
        }
        if (trim($this->codigo) === '') {
            throw new DomainException('El código de la pasarela no puede estar vacío.');
        }
    }

    // Comportamiento
    public function estaActiva(): bool { return $this->activa; }
    public function activar(): void { $this->activa = true; }
    public function desactivar(): void { $this->activa = false; }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getNombre(): string { return $this->nombre; }
    public function getCodigo(): string { return $this->codigo; }
}