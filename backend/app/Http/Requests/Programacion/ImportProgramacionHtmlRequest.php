<?php

namespace App\Http\Requests\Programacion;

use Illuminate\Foundation\Http\FormRequest;

class ImportProgramacionHtmlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file'       => ['required', 'file', 'mimes:htm,html', 'max:10240'],
            'periodo_id' => ['nullable', 'uuid', 'exists:periodos,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'El archivo HTML es requerido.',
            'file.mimes'    => 'El archivo debe ser un reporte HTML del SIGA (.htm o .html).',
            'file.max'      => 'El archivo no debe superar los 10 MB.',
            'periodo_id.uuid'   => 'El ID del periodo debe ser un UUID válido.',
            'periodo_id.exists' => 'El periodo seleccionado no existe.',
        ];
    }
}
