<?php
namespace App\Domain\Entities\Recintos;

use App\Domain\Enums\Recintos\EstadoEvento;
use DateTimeImmutable;
use DomainException;

class Evento
{
    public function __construct(
        private ?int $id,
        private int $recintoId,
        private string $nombre,
        private DateTimeImmutable $fechaEvento,
        private EstadoEvento $estado,
        private ?string $descripcion = null,
        private ?DateTimeImmutable $fechaPublicacion = null,
    ) {
        $this->validar();
    }

    private function validar(): void
    {
        if (trim($this->nombre) === '') {
            throw new DomainException('El nombre del evento no puede estar vacío.');
        }
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getRecintoId(): int { return $this->recintoId; }
    public function getNombre(): string { return $this->nombre; }
    public function getFechaEvento(): DateTimeImmutable { return $this->fechaEvento; }
    public function getEstado(): EstadoEvento { return $this->estado; }
    public function getDescripcion(): ?string { return $this->descripcion; }
    public function getFechaPublicacion(): ?DateTimeImmutable { return $this->fechaPublicacion; }

    // Comportamiento de negocio
    public function estaPublicado(): bool
    {
        return $this->estado === EstadoEvento::PUBLICADO;
    }

    public function sePuedeVender(): bool
    {
        return $this->estado === EstadoEvento::PUBLICADO;
    }
}