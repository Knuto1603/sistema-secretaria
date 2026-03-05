<?php

namespace App\Http\Requests\Estudiante;

use Illuminate\Foundation\Http\FormRequest;

class VerificarOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'size:10', 'regex:/^\d{10}$/'],
            'otp' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.required' => 'El código universitario es requerido.',
            'codigo.size' => 'El código universitario debe tener 10 dígitos.',
            'otp.required' => 'El código de verificación es requerido.',
            'otp.size' => 'El código de verificación debe tener 6 dígitos.',
            'otp.regex' => 'El código de verificación debe contener solo números.',
        ];
    }
}
