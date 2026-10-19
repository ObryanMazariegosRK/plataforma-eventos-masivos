<?php
namespace App\Domain\Abstractions\Pasarelas;

/** Respuesta de la pasarela al crear una orden (paso 1). */
class OrdenPasarela
{
    public function __construct(
        public readonly string $id,               // id de la orden en la pasarela
        public readonly string $urlAprobacion,    // a dónde mandar el navegador para que la persona apruebe
    ) {}
}
