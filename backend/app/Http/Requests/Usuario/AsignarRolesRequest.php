<?php

namespace App\Http\Requests\Usuario;

use Illuminate\Foundation\Http\FormRequest;

class AsignarRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'roles' => ['required', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
        ];
    }

    public function messages(): array
    {
        return [
            'roles.required' => 'Debe proporcionar al menos un rol.',
            'roles.array' => 'Los roles deben ser una lista.',
            'roles.*.exists' => 'Uno o más roles seleccionados no existen.',
        ];
    }
}
