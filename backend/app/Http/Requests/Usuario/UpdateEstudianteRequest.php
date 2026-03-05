<?php

namespace App\Http\Requests\Usuario;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEstudianteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'escuela_id' => ['sometimes', 'nullable', 'uuid', 'exists:escuelas,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.max' => 'El nombre no puede exceder 255 caracteres.',
            'escuela_id.uuid' => 'El ID de escuela debe ser un UUID válido.',
            'escuela_id.exists' => 'La escuela seleccionada no existe.',
        ];
    }
}
