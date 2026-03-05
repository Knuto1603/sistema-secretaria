<?php

namespace App\Http\Requests\Solicitud;

use Illuminate\Foundation\Http\FormRequest;

class CreateSolicitudRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'programacion_id' => ['required', 'uuid', 'exists:programacion_academica,id'],
            'motivo' => ['required', 'string', 'min:20'],
            'firma' => ['required', 'string'],
            'archivo_sustento' => ['nullable', 'file', 'mimes:pdf,jpg,png', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'programacion_id.required' => 'La programación es requerida.',
            'programacion_id.uuid' => 'El ID de programación debe ser un UUID válido.',
            'programacion_id.exists' => 'La programación seleccionada no existe.',
            'motivo.required' => 'El motivo es requerido.',
            'motivo.min' => 'El motivo debe tener al menos 20 caracteres.',
            'firma.required' => 'La firma digital es requerida.',
            'archivo_sustento.mimes' => 'El archivo debe ser PDF, JPG o PNG.',
            'archivo_sustento.max' => 'El archivo no debe superar los 2MB.',
        ];
    }
}
