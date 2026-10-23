<?php

namespace App\Domain\Entities\Bitacora;

use App\Domain\Enums\Bitacora\CategoriaBitacora;
use App\Domain\Enums\Bitacora\ResultadoBitacora;
use DateTimeImmutable;
use DomainException;

// Una fila de la bitácora: quién hizo qué, cuándo y con qué resultado. Es inmutable:
// se crea una vez y nunca cambia (la base además rechaza UPDATE y DELETE).
class RegistroBitacora
{
    public function __construct(
        private DateTimeImmutable $ocurridoEn,
        private CategoriaBitacora $categoria,
        private string $accion,
        private ResultadoBitacora $resultado,
        private string $descripcion,
        private ?int $usuarioId = null,
        private ?string $usuarioNombre = null,
        private ?string $usuarioCorreo = null,
        private ?string $usuarioRol = null,
        private ?string $entidad = null,
        private ?string $entidadId = null,
        private ?array $datosAntes = null,
        private ?array $datosDespues = null,
        private ?string $ip = null,
        private ?string $userAgent = null,
    ) {
        $this->validar();
    }

    private function validar(): void
    {
        if (! preg_match('/^[a-z_]+\.[a-z_]+$/', $this->accion)) {
            throw new DomainException('La acción de la bitácora debe tener la forma "area.accion".');
        }

        if (trim($this->descripcion) === '') {
            throw new DomainException('Todo registro de la bitácora necesita una descripción.');
        }
    }

    public function fueExitoso(): bool
    {
        return $this->resultado === ResultadoBitacora::EXITO;
    }

    public function getOcurridoEn(): DateTimeImmutable
    {
        return $this->ocurridoEn;
    }

    public function getCategoria(): CategoriaBitacora
    {
        return $this->categoria;
    }

    public function getAccion(): string
    {
        return $this->accion;
    }

    public function getResultado(): ResultadoBitacora
    {
        return $this->resultado;
    }

    public function getDescripcion(): string
    {
        return $this->descripcion;
    }

    public function getUsuarioId(): ?int
    {
        return $this->usuarioId;
    }

    public function getUsuarioNombre(): ?string
    {
        return $this->usuarioNombre;
    }

    public function getUsuarioCorreo(): ?string
    {
        return $this->usuarioCorreo;
    }

    public function getUsuarioRol(): ?string
    {
        return $this->usuarioRol;
    }

    public function getEntidad(): ?string
    {
        return $this->entidad;
    }

    public function getEntidadId(): ?string
    {
        return $this->entidadId;
    }

    public function getDatosAntes(): ?array
    {
        return $this->datosAntes;
    }

    public function getDatosDespues(): ?array
    {
        return $this->datosDespues;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }
}
