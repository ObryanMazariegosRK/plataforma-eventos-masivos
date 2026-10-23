<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarEventoRequest extends FormRequest
{
    /**
     * La autenticación y autorización administrativa
     * se verifican mediante los middleware:
     *
     * auth:sanctum
     * rol:admin
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Validaciones para crear y editar eventos.
     *
     * La creación siempre inicia como borrador.
     * La publicación se realiza mediante su
     * propio caso de uso.
     */
    public function rules(): array
    {
        return [
            'nombre' => [
                'required',
                'string',
                'min:3',
                'max:255',
            ],

            'descripcion' => [
                'nullable',
                'string',
                'max:10000',
            ],

            // Fotos subidas desde el formulario (o URL HTTPS en eventos antiguos)
            'imagen_url' => ['required', 'string', new \App\Rules\ImagenDeEvento],

            // Fotos subidas desde el formulario (o URL HTTPS en eventos antiguos)
            'imagen_url_2' => ['required', 'string', new \App\Rules\ImagenDeEvento],

            // Fotos subidas desde el formulario (o URL HTTPS en eventos antiguos)
            'imagen_url_3' => ['required', 'string', new \App\Rules\ImagenDeEvento],

            'recinto_id' => [
                'required',
                'integer',

                Rule::exists('recintos', 'id')
                    ->where('estado', 'activo')
                    ->whereNull('deleted_at'),
            ],

            'fecha_evento' => [
                'required',
                'date',
            ],

            'estado' => [
                'sometimes',
                Rule::in([
                    'borrador',
                ]),
            ],
        ];
    }

    /**
     * Mensajes de validación en español.
     */
    public function messages(): array
    {
        return [
            'imagen_url.required' =>
                'Sube la foto principal del evento (portada y primera escena).',
            'imagen_url_2.required' =>
                'Sube la segunda foto del evento.',
            'imagen_url_3.required' =>
                'Sube la tercera foto del evento.',
            'nombre.required' =>
                'Debes ingresar el nombre del evento.',

            'nombre.min' =>
                'El nombre debe tener al menos 3 caracteres.',

            'nombre.max' =>
                'El nombre no puede superar 255 caracteres.',

            'descripcion.max' =>
                'La descripción es demasiado extensa.',

            'imagen_url.url' =>
                'Debes ingresar una URL válida para la imagen.',

            'imagen_url.starts_with' =>
                'La imagen debe utilizar un enlace HTTPS.',

            'imagen_url.max' =>
                'La URL de la imagen no puede superar 2048 caracteres.',

            'recinto_id.required' =>
                'Debes seleccionar un recinto.',

            'recinto_id.integer' =>
                'El recinto seleccionado no es válido.',

            'recinto_id.exists' =>
                'El recinto seleccionado no existe o no está activo.',

            'fecha_evento.required' =>
                'Debes seleccionar la fecha del evento.',

            'fecha_evento.date' =>
                'Debes ingresar una fecha válida.',

            'estado.in' =>
                'Los eventos se guardan inicialmente como borradores.',
        ];
    }

    /**
     * Nombres amigables para los campos.
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre del evento',
            'descripcion' => 'descripción',
            'imagen_url' => 'imagen del evento',
            'recinto_id' => 'recinto',
            'fecha_evento' => 'fecha del evento',
            'estado' => 'estado del evento',
        ];
    }
}
