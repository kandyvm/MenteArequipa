<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegistroJovenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
        protected function prepareForValidation(): void
    {
        if ($this->has('contrasena') && ! $this->has('password')) {
            $this->merge(['password' => $this->input('contrasena')]);
        }
    }

    public function rules(): array
    {
        return [
            'correo' => ['required', 'email', 'max:150', 'unique:usuarios,correo'],
            'password' => ['required', 'string', 'min:10'],
            // Edad de 18 a 29 años, validada en el servidor
            'fecha_nacimiento' => [
                'required', 'date',
                'before_or_equal:' . now()->subYears(18)->toDateString(),
                'after:' . now()->subYears(30)->toDateString(),
            ],
            'distrito' => ['required', 'string', 'max:60'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'acepta_consentimiento' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_nacimiento.before_or_equal' => 'Debes tener al menos 18 años.',
            'fecha_nacimiento.after' => 'La plataforma es para personas de 18 a 29 años.',
            'acepta_consentimiento.accepted' => 'Debes aceptar el consentimiento informado.',
        ];
    }
}
