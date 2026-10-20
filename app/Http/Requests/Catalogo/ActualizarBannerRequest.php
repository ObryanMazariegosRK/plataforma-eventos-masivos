<?php

namespace App\Http\Requests\Catalogo;

use App\Domain\Enums\Catalogo\TipoBanner;
use Illuminate\Foundation\Http\FormRequest;

class ActualizarBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // la ruta ya exige auth:sanctum + rol:admin
    }

    public function rules(): array
    {
        return [
            'archivo' => [
                'required',
                'file',
                'mimetypes:' . implode(',', array_keys(TipoBanner::MIMES)),
                'max:' . config('catalogo.banner.max_mb') * 1024,   // en KB
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'archivo.required'  => 'Elige una imagen o un video.',
            'archivo.uploaded'  => 'El archivo no se pudo subir: puede que pese demasiado.',
            'archivo.mimetypes' => 'El banner debe ser una imagen (JPG, PNG, WEBP) o un video (MP4, WEBM).',
            'archivo.max'       => 'El archivo no puede pesar más de ' . config('catalogo.banner.max_mb') . ' MB.',
        ];
    }
}
