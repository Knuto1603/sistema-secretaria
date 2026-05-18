<?php

namespace App\Http\Requests\Modificacion;

use Illuminate\Foundation\Http\FormRequest;

class AbrirSeccionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'curso_id'             => ['required', 'uuid', 'exists:cursos,id'],
            'periodo_id'           => ['required', 'uuid', 'exists:periodos,id'],
            'capacidad'            => ['required', 'integer', 'min:1', 'max:500'],
            'grupo'                => ['required', 'string', 'max:50'],
            'ciclo'                => ['nullable', 'integer', 'min:1', 'max:10'],
            'aula_id'              => ['nullable', 'uuid', 'exists:aulas,id'],
            'grupo_horario_id'     => ['nullable', 'uuid', 'exists:grupos_horario,id'],
            'docente_id'           => ['nullable', 'uuid', 'exists:docentes,id'],
            'escuela_programada_id'=> ['nullable', 'uuid', 'exists:escuelas,id'],
            'motivo'               => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'curso_id.required'    => 'El curso es obligatorio.',
            'curso_id.exists'      => 'El curso seleccionado no existe.',
            'periodo_id.required'  => 'El periodo es obligatorio.',
            'periodo_id.exists'    => 'El periodo seleccionado no existe.',
            'capacidad.required'   => 'La capacidad es obligatoria.',
            'capacidad.min'        => 'La capacidad debe ser al menos 1.',
            'grupo.required'       => 'El grupo es obligatorio.',
            'motivo.required'      => 'El motivo de apertura es obligatorio.',
            'motivo.min'           => 'El motivo debe tener al menos 10 caracteres.',
        ];
    }
}
