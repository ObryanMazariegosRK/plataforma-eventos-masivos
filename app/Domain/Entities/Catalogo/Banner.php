<?php
namespace App\Domain\Entities\Catalogo;

use App\Domain\Enums\Catalogo\TipoBanner;
use DomainException;

class Banner
{
    public function __construct(
        private ?int $id,
        private TipoBanner $tipo,
        private string $ruta,
        private ?int $actualizadoPor = null,
    ) {
        $this->validar();
    }

    private function validar(): void
    {
        if (trim($this->ruta) === '') {
            throw new DomainException('La ruta del archivo del banner es obligatoria.');
        }
    }

    public function getId(): ?int { return $this->id; }
    public function getTipo(): TipoBanner { return $this->tipo; }
    public function getRuta(): string { return $this->ruta; }
    public function getActualizadoPor(): ?int { return $this->actualizadoPor; }
    public function esVideo(): bool { return $this->tipo === TipoBanner::VIDEO; }
}
