<?php

namespace App\Http\Requests\TipoSolicitud;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTipoSolicitudRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'codigo' => ['sometimes', 'string', 'max:20', Rule::unique('tipo_solicitudes', 'codigo')->ignore($id)],
            'nombre' => ['sometimes', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'requiere_archivo' => ['nullable', 'boolean'],
            'activo' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.unique' => 'Este código ya está en uso.',
            'codigo.max' => 'El código no puede exceder 20 caracteres.',
            'nombre.max' => 'El nombre no puede exceder 100 caracteres.',
            'descripcion.max' => 'La descripción no puede exceder 500 caracteres.',
        ];
    }
}
