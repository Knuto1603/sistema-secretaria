<?php

namespace App\Http\Requests\Periodo;

use Illuminate\Foundation\Http\FormRequest;

class CreatePeriodoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255', 'unique:periodos,nombre'],
            'fecha_inicio' => ['nullable', 'date', 'date_format:Y-m-d'],
            'fecha_fin' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:fecha_inicio'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del periodo es requerido.',
            'nombre.string' => 'El nombre del periodo debe ser una cadena de texto.',
            'nombre.max' => 'El nombre del periodo no debe superar los 255 caracteres.',
            'nombre.unique' => 'Ya existe un periodo con este nombre.',
            'fecha_inicio.date' => 'La fecha de inicio debe ser una fecha válida.',
            'fecha_inicio.date_format' => 'La fecha de inicio debe tener el formato YYYY-MM-DD.',
            'fecha_fin.date' => 'La fecha de fin debe ser una fecha válida.',
            'fecha_fin.date_format' => 'La fecha de fin debe tener el formato YYYY-MM-DD.',
            'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'activo.boolean' => 'El campo activo debe ser verdadero o falso.',
        ];
    }
}
