<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

// Imagen de un evento: la ruta de una imagen subida desde el panel (/storage/eventos/...)
// o, para eventos creados antes de la subida de archivos, una URL HTTPS.
class ImagenDeEvento implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $valor = (string) $value;
        $subida = preg_match('#^/storage/eventos/[A-Za-z0-9_\-./]+\.(webp|jpe?g|png)$#', $valor) === 1 && ! str_contains($valor, '..');
        $externa = str_starts_with($valor, 'https://') && filter_var($valor, FILTER_VALIDATE_URL) !== false && strlen($valor) <= 2048;

        if (! $subida && ! $externa) {
            $fail('La imagen no es válida: súbela de nuevo desde el formulario.');
        }
    }
}
