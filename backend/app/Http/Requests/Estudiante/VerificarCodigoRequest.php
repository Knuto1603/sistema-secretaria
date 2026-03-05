<?php

namespace App\Http\Requests\Estudiante;

use Illuminate\Foundation\Http\FormRequest;

class VerificarCodigoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'size:10', 'regex:/^\d{10}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.required' => 'El código universitario es requerido.',
            'codigo.size' => 'El código universitario debe tener 10 dígitos.',
            'codigo.regex' => 'El código universitario debe contener solo números.',
        ];
    }
}
