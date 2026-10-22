<?php

namespace App\Application\DTOs\Bitacora;

class BuscarEnBitacoraDTO
{
    public function __construct(
        public readonly int $pagina,
        public readonly int $porPagina,
        public readonly string $zonaHoraria,          // los días del filtro son de esta zona; la base guarda UTC
        public readonly ?string $categoria = null,
        public readonly ?string $resultado = null,
        public readonly ?string $rol = null,          // admin | organizador | cliente | sin_sesion
        public readonly ?string $usuario = null,      // nombre o correo (contiene)
        public readonly ?string $desde = null,        // Y-m-d
        public readonly ?string $hasta = null,        // Y-m-d (incluido)
    ) {}
}
