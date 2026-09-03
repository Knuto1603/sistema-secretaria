<?php

namespace App\Http\Requests\SolicitudApertura;

use Illuminate\Foundation\Http\FormRequest;

class CreateSolicitudAperturaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'curso_id' => ['required', 'uuid', 'exists:cursos,id'],
            'tipo' => ['nullable', 'in:nueva_apertura,cambio_grupo'],
            'programacion_referencia_id' => [
                'nullable', 'uuid', 'exists:programacion_secciones,id',
                'required_if:tipo,cambio_grupo',
            ],
            'motivo' => ['required', 'string', 'min:20'],
            'firma' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'curso_id.required' => 'El curso es requerido.',
            'curso_id.exists' => 'El curso seleccionado no existe.',
            'programacion_referencia_id.required_if' => 'Debes indicar con qué sección existente cruza tu horario.',
            'motivo.required' => 'El motivo es requerido.',
            'motivo.min' => 'El motivo debe tener al menos 20 caracteres.',
            'firma.required' => 'La firma digital es requerida.',
        ];
    }
}
