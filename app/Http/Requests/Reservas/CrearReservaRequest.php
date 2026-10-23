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
            // Asientos numerados (por id) y/o cupos de localidades generales (por cantidad): al menos uno
            'inventario_ids' => ['required_without:cupos', 'array'],
            'inventario_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:inventario,id',
            ],
            'cupos' => ['required_without:inventario_ids', 'array'],
            'cupos.*.localidad_id' => ['required', 'integer', 'distinct', 'exists:localidades,id'],
            'cupos.*.cantidad' => ['required', 'integer', 'min:1'],

        ];
    }
}
