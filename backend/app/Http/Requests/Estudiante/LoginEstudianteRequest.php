<?php

namespace App\Http\Requests\Estudiante;

use Illuminate\Foundation\Http\FormRequest;

class LoginEstudianteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'size:10', 'regex:/^\d{10}$/'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.required' => 'El código universitario es requerido.',
            'codigo.size' => 'El código universitario debe tener 10 dígitos.',
            'codigo.regex' => 'El código universitario debe contener solo números.',
            'password.required' => 'La contraseña es requerida.',
            'device_name.required' => 'El nombre del dispositivo es requerido.',
        ];
    }
}
