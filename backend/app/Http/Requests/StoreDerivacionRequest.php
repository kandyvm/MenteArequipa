<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDerivacionRequest extends FormRequest
{
    /** Solo el ORIENTADOR deriva (un joven recibe 403). */
    public function authorize(): bool
    {
        return $this->user()?->rol === 'ORIENTADOR';
    }

    public function rules(): array
    {
        return [
            'centro_id' => ['required', 'integer', Rule::exists('centros', 'id')->where('activo', true)],
            'prioridad' => ['required', Rule::in([1, 3, 5])],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }
}