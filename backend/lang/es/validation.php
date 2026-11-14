<?php

// Mensajes de validación en español. Solo las reglas que usa la API;
// cualquier regla que falte cae al inglés (APP_FALLBACK_LOCALE=en).
return [
    'accepted' => 'Debes aceptar :attribute.',
    'between' => ['numeric' => ':Attribute debe estar entre :min y :max.'],
    'boolean' => ':Attribute debe ser verdadero o falso.',
    'date' => ':Attribute no es una fecha válida.',
    'email' => ':Attribute debe ser un correo válido.',
    'exists' => ':Attribute seleccionado no existe.',
    'in' => ':Attribute no es válido.',
    'integer' => ':Attribute debe ser un número entero.',
    'max' => ['string' => ':Attribute no debe superar :max caracteres.', 'numeric' => ':Attribute no debe ser mayor que :max.'],
    'min' => ['string' => ':Attribute debe tener al menos :min caracteres.', 'numeric' => ':Attribute debe ser al menos :min.'],
    'numeric' => ':Attribute debe ser un número.',
    'required' => ':Attribute es obligatorio.',
    'required_if' => ':Attribute es obligatorio cuando :other es :value.',
    'string' => ':Attribute debe ser texto.',
    'unique' => 'Ese :attribute ya está registrado.',
    'url' => ':Attribute debe ser un enlace válido (http o https).',

    'attributes' => [
        'correo' => 'correo',
        'password' => 'contraseña',
        'password_actual' => 'contraseña actual',
        'fecha_nacimiento' => 'fecha de nacimiento',
        'distrito' => 'distrito',
        'telefono' => 'teléfono',
        'acepta_consentimiento' => 'el consentimiento informado',
        'motivo' => 'motivo',
        'descripcion' => 'descripción',
        'nivel_riesgo' => 'nivel de riesgo',
        'canal' => 'canal',
        'centro_id' => 'centro',
        'prioridad' => 'prioridad',
        'observaciones' => 'observaciones',
        'nombre' => 'nombre',
        'direccion' => 'dirección',
        'titulo' => 'título',
        'url' => 'enlace',
    ],
];
