<?php

namespace App\Http\Requests\Estudiante;

use Illuminate\Foundation\Http\FormRequest;

class ImportAlumnosHtmlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:htm,html', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'El archivo HTML es requerido.',
            'file.mimes'    => 'El archivo debe ser un padrón HTML del SIGA (.htm o .html).',
            'file.max'      => 'El archivo no debe superar los 10 MB.',
        ];
    }
}
