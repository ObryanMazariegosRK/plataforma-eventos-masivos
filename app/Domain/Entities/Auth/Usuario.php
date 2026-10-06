<?php
namespace App\Domain\Entities\Auth;

use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use DomainException;

class Usuario
{
    public function __construct(
        private ?int $id,
        private string $nombre,
        private string $apellido,
        private string $email,
        private RolUsuario $rol,
        private EstadoUsuario $estado,
        private ?string $telefono = null,
        private ?string $passwordHash = null,
    ) {
        $this->validar();          // la entidad se protege al nacer
    }

    private function validar(): void
    {
        if (trim($this->nombre) === '') {
            throw new DomainException('El nombre no puede estar vacío.');
        }
        if (trim($this->apellido) === '') {
            throw new DomainException('El apellido no puede estar vacío.');
        }
        if (! filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('El correo no tiene un formato válido.');
        }
        if ($this->telefono !== null && ! preg_match('/^[0-9+\-\s]{8,15}$/', $this->telefono)) {
            throw new DomainException('El teléfono no tiene un formato válido.');
        }
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getNombre(): string { return $this->nombre; }
    public function getApellido(): string { return $this->apellido; }
    public function getEmail(): string { return $this->email; }
    public function getRol(): RolUsuario { return $this->rol; }
    public function getEstado(): EstadoUsuario { return $this->estado; }
    public function getTelefono(): ?string { return $this->telefono; }
    public function getPasswordHash(): ?string { return $this->passwordHash; }

    // Comportamiento de negocio
    public function estaBloqueado(): bool
    {
        return $this->estado === EstadoUsuario::BLOQUEADO;
    }

    public function nombreCompleto(): string
    {
        return "{$this->nombre} {$this->apellido}";
    }
}