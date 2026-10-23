<?php

namespace App\Application\DTOs\Bitacora;

use App\Domain\Enums\Bitacora\CategoriaBitacora;

class RegistrarEnBitacoraDTO
{
    public function __construct(
        public readonly CategoriaBitacora $categoria,
        public readonly string $accion,
        public readonly bool $exito,
        public readonly string $descripcion,
        public readonly ?int $usuarioId = null,
        public readonly ?string $usuarioNombre = null,
        public readonly ?string $usuarioCorreo = null,
        public readonly ?string $usuarioRol = null,
        public readonly ?string $entidad = null,
        public readonly ?string $entidadId = null,
        public readonly ?array $datosAntes = null,
        public readonly ?array $datosDespues = null,
        public readonly ?string $ip = null,
        public readonly ?string $userAgent = null,
    ) {}
}
