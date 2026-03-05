<?php

namespace App\Http\Requests\TipoSolicitud;

use Illuminate\Foundation\Http\FormRequest;

class CreateTipoSolicitudRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:20', 'unique:tipo_solicitudes,codigo'],
            'nombre' => ['required', 'string', 'max:100'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'requiere_archivo' => ['nullable', 'boolean'],
            'activo' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'codigo.required' => 'El código es requerido.',
            'codigo.unique' => 'Este código ya está en uso.',
            'codigo.max' => 'El código no puede exceder 20 caracteres.',
            'nombre.required' => 'El nombre es requerido.',
            'nombre.max' => 'El nombre no puede exceder 100 caracteres.',
            'descripcion.max' => 'La descripción no puede exceder 500 caracteres.',
        ];
    }
}
