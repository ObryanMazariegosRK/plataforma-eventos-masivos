<?php
namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerificarCorreoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'  => ['required', 'email'],
            'codigo' => ['required', 'string', 'digits:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'  => 'El correo es obligatorio.',
            'email.email'     => 'El correo no tiene un formato válido.',
            'codigo.required' => 'El código es obligatorio.',
            'codigo.digits'   => 'El código debe tener 6 dígitos.',
        ];
    }
}
