<?php
namespace App\Http\Requests\Pagos;

use Illuminate\Foundation\Http\FormRequest;

class IniciarPagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // la autenticación la hace auth:sanctum; la propiedad de la reserva, el caso de uso
    }

    public function rules(): array
    {
        return [
            'reserva_id' => ['required', 'integer', 'gt:0'],
            'pasarela'   => ['required', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'reserva_id.required' => 'Indica la reserva que vas a pagar.',
            'pasarela.required'   => 'Elige una pasarela de pago.',
        ];
    }
}
