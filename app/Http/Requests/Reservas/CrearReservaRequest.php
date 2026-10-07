<?php
namespace App\Http\Requests\Reservas;

use Illuminate\Foundation\Http\FormRequest;

class CrearReservaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // sin autenticación por ahora (llega con el módulo Auth)
    }

    public function rules(): array
    {
        return [
            'usuario_id' => ['required', 'integer', 'gt:0', 'exists:usuarios,id'],
            'evento_id'  => ['required', 'integer', 'gt:0', 'exists:eventos,id'],
            'total'      => ['required', 'numeric', 'gt:0'],
        ];
    }
}
