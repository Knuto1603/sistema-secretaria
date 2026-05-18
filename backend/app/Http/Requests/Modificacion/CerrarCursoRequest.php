<?php

namespace App\Http\Requests\Modificacion;

use Illuminate\Foundation\Http\FormRequest;

class CerrarCursoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'El motivo del cierre es obligatorio.',
            'motivo.min'      => 'El motivo debe tener al menos 10 caracteres.',
        ];
    }
}
