<?php

namespace App\Http\Requests\Modificacion;

use Illuminate\Foundation\Http\FormRequest;

class UnificarSeccionesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'programacion_destino_id'  => ['required', 'uuid', 'exists:programacion_academica,id'],
            'secciones_origen_ids'     => ['required', 'array', 'min:1'],
            'secciones_origen_ids.*'   => ['required', 'uuid', 'exists:programacion_academica,id', 'different:programacion_destino_id'],
            'motivo'                   => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'programacion_destino_id.required'  => 'La sección destino es obligatoria.',
            'programacion_destino_id.exists'     => 'La sección destino no existe.',
            'secciones_origen_ids.required'      => 'Debe seleccionar al menos una sección origen.',
            'secciones_origen_ids.min'           => 'Debe seleccionar al menos una sección origen.',
            'secciones_origen_ids.*.exists'      => 'Una o más secciones origen no existen.',
            'secciones_origen_ids.*.different'   => 'La sección origen no puede ser la misma que la destino.',
            'motivo.required'                    => 'El motivo de la unificación es obligatorio.',
            'motivo.min'                         => 'El motivo debe tener al menos 10 caracteres.',
        ];
    }
}
