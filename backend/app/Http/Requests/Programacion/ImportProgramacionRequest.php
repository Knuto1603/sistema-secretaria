<?php

namespace App\Http\Requests\Programacion;

use Illuminate\Foundation\Http\FormRequest;

class ImportProgramacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'mimes:xlsx,xls,csv'],
            'periodo_id' => ['nullable', 'uuid', 'exists:periodos,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'El archivo Excel es requerido.',
            'file.mimes' => 'El archivo debe ser de tipo Excel (xlsx, xls) o CSV.',
            'periodo_id.uuid' => 'El ID del periodo debe ser un UUID válido.',
            'periodo_id.exists' => 'El periodo seleccionado no existe.',
        ];
    }
}
