<?php

namespace App\Http\Requests\Modificacion;

use Illuminate\Foundation\Http\FormRequest;

class CambioAulaYGrupoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'aula_id'          => ['required', 'uuid', 'exists:aulas,id'],
            'grupo_horario_id' => ['required', 'uuid', 'exists:grupos_horario,id'],
            'motivo'           => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'aula_id.required'          => 'El aula nueva es obligatoria.',
            'aula_id.exists'            => 'El aula seleccionada no existe.',
            'grupo_horario_id.required' => 'El grupo horario nuevo es obligatorio.',
            'grupo_horario_id.exists'   => 'El grupo horario seleccionado no existe.',
            'motivo.required'           => 'El motivo del cambio es obligatorio.',
            'motivo.min'                => 'El motivo debe tener al menos 10 caracteres.',
        ];
    }
}
