<?php

namespace App\Domain\Enums\Finanzas;

enum CategoriaCosto: string
{
    case RECINTO = 'recinto';      // alquiler del lugar
    case ARTISTAS = 'artistas';     // honorarios
    case PRODUCCION = 'produccion';   // sonido, luces, escenografía
    case PUBLICIDAD = 'publicidad';
    case PERSONAL = 'personal';     // seguridad, acomodadores, taquilla
    case OTROS = 'otros';

    public function etiqueta(): string
    {
        return match ($this) {
            self::RECINTO => 'Recinto',
            self::ARTISTAS => 'Artistas',
            self::PRODUCCION => 'Producción',
            self::PUBLICIDAD => 'Publicidad',
            self::PERSONAL => 'Personal',
            self::OTROS => 'Otros',
        };
    }
}
