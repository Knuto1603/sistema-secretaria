<?php

namespace App\Http\Requests\Curso;

use Illuminate\Foundation\Http\FormRequest;

class CreateCursoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:50'],
            'nombre' => ['required', 'string', 'max:255'],
            'area_id' => ['sometimes', 'nullable', 'uuid', 'exists:areas,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.required' => 'El código del curso es requerido.',
            'codigo.string' => 'El código del curso debe ser una cadena de texto.',
            'codigo.max' => 'El código del curso no debe superar los 50 caracteres.',
            'nombre.required' => 'El nombre del curso es requerido.',
            'nombre.string' => 'El nombre del curso debe ser una cadena de texto.',
            'nombre.max' => 'El nombre del curso no debe superar los 255 caracteres.',
            'area_id.uuid' => 'El área debe ser un identificador válido.',
            'area_id.exists' => 'El área seleccionada no existe.',
        ];
    }
}
