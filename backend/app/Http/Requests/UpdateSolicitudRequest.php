<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UpdateSolicitudRequest extends FormRequest
{
    /** La policy decide quién puede editar (si no puede, devuelve 403). */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('solicitud'));
    }

    public function rules(): array
    {
        // El orientador solo cambia el estado
        if ($this->user()->rol === 'ORIENTADOR') {
            return ['estado' => ['required', Rule::in(['EN_REVISION', 'CERRADA'])]];
        }

        // El joven edita sus datos mientras esté REGISTRADA
        return [
            'motivo' => ['sometimes', 'string', 'max:40'],
            'descripcion' => ['sometimes', 'string', 'max:500'],
            'nivel_riesgo' => ['sometimes', Rule::in(['BAJO', 'MEDIO', 'ALTO', 'CRISIS'])],
            'canal' => ['sometimes', Rule::in(['WEB', 'PRESENCIAL'])],
        ];
    }
}