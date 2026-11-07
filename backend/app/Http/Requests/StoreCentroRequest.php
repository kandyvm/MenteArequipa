<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCentroRequest extends FormRequest
{
    /** Solo el ADMIN da de alta centros. */
    public function authorize(): bool
    {
        return $this->user()?->rol === 'ADMIN';
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150'],
            'tipo' => ['required', Rule::in(['MINSA', 'ESSALUD', 'ONG', 'PRIVADO'])],
            'distrito' => ['required', 'string', 'max:60'],
            'direccion' => ['required', 'string', 'max:200'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],
            'servicios' => ['nullable', 'string', 'max:1000'],
        ];
    }
}