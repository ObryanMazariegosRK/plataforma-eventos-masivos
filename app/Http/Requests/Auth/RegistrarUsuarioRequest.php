<?php
namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegistrarUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre'   => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
            // sin 'unique': el caso de uso lo valida y responde 409
            'email'    => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', Password::defaults()],   // reglas en AppServiceProvider::boot()
            'telefono' => ['nullable', 'string', 'regex:/^[0-9+\-\s]{8,15}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required'   => 'El nombre es obligatorio.',
            'apellido.required' => 'El apellido es obligatorio.',
            'email.required'    => 'El correo es obligatorio.',
            'email.email'       => 'El correo no tiene un formato válido.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min'      => 'La contraseña debe tener al menos 8 caracteres.',
            'password.mixed'    => 'La contraseña debe incluir mayúsculas y minúsculas.',
            'password.numbers'  => 'La contraseña debe incluir al menos un número.',
            'password.symbols'  => 'La contraseña debe incluir al menos un símbolo (@, #, $, %...).',
            'telefono.regex'    => 'El teléfono debe tener entre 8 y 15 dígitos.',
        ];
    }
}
