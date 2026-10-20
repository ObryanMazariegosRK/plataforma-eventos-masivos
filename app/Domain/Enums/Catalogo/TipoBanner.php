<?php
namespace App\Domain\Enums\Catalogo;

enum TipoBanner: string
{
    case IMAGEN = 'imagen';
    case VIDEO  = 'video';

    // Tipos de archivo aceptados para cada uno (los mismos que valida el request)
    public const MIMES = [
        'image/jpeg' => self::IMAGEN,
        'image/png'  => self::IMAGEN,
        'image/webp' => self::IMAGEN,
        'video/mp4'  => self::VIDEO,
        'video/webm' => self::VIDEO,
    ];

    public static function desdeMime(string $mime): ?self
    {
        return self::MIMES[strtolower($mime)] ?? null;
    }
}
