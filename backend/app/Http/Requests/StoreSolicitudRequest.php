<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSolicitudRequest extends FormRequest
{
    /** Solo un JOVEN puede crear solicitudes. */
    public function authorize(): bool
    {
        return $this->user()?->rol === 'JOVEN';
    }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'max:40'],
            'descripcion' => ['required', 'string', 'max:500'],
            'nivel_riesgo' => ['required', Rule::in(['BAJO', 'MEDIO', 'ALTO', 'CRISIS'])],
            'canal' => ['required', Rule::in(['WEB', 'PRESENCIAL'])],
            'acepta_consentimiento' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'acepta_consentimiento.accepted' => 'Debes aceptar el consentimiento informado.',
        ];
    }
}