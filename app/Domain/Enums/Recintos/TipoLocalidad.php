<?php
namespace App\Domain\Enums\Recintos;

enum TipoLocalidad: string
{
    case NUMERADA = 'numerada';   // tiene asientos individuales
    case GENERAL  = 'general';    // solo un cupo, sin asientos
}