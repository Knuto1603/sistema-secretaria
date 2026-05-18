<?php

namespace App\Http\Requests\Modificacion;

use Illuminate\Foundation\Http\FormRequest;

class CambioGrupoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'grupo_horario_id' => ['required', 'uuid', 'exists:grupos_horario,id'],
            'motivo'           => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'grupo_horario_id.required' => 'El grupo horario nuevo es obligatorio.',
            'grupo_horario_id.exists'   => 'El grupo horario seleccionado no existe.',
            'motivo.required'           => 'El motivo del cambio es obligatorio.',
            'motivo.min'                => 'El motivo debe tener al menos 10 caracteres.',
        ];
    }
}
