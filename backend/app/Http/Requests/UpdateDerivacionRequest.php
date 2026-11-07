<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateDerivacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('derivacion'));
    }

    public function rules(): array
    {
        // El centro receptor cambia el estado
        if ($this->user()->rol === 'CENTRO') {
            return [
                'estado' => ['required', Rule::in(['ACEPTADA', 'RECHAZADA', 'ATENDIDA'])],
                'nota' => ['nullable', 'string', 'max:500'],
            ];
        }

        // El orientador corrige prioridad u observaciones (solo si está PENDIENTE)
        return [
            'prioridad' => ['sometimes', Rule::in([1, 3, 5])],
            'observaciones' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}