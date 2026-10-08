<?php
namespace App\Http\Requests\Reservas;

use Illuminate\Foundation\Http\FormRequest;

class CrearReservaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la autenticación la hace auth:sanctum en la ruta
    }

    public function rules(): array
    {
        return [
            'evento_id'  => ['required', 'integer', 'gt:0', 'exists:eventos,id'],
            'total'      => ['required', 'numeric', 'gt:0'],
        ];
    }
}
