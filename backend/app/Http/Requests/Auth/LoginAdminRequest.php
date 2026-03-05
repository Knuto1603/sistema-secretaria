<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:3', 'max:50'],
            'password' => ['required', 'string'],
            'device_name' => ['sometimes', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => 'El nombre de usuario es requerido.',
            'username.min' => 'El nombre de usuario debe tener al menos 3 caracteres.',
            'password.required' => 'La contraseña es requerida.',
        ];
    }

    /**
     * Prepara los datos antes de la validación
     */
    protected function prepareForValidation(): void
    {
        // Si no se envía device_name, usar uno por defecto
        if (!$this->has('device_name')) {
            $this->merge([
                'device_name' => 'web-browser',
            ]);
        }
    }
}
