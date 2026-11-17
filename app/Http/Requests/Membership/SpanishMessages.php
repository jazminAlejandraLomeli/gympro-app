<?php

namespace App\Http\Requests\Membership;

/** Mensajes de validación en español (el proyecto no trae lang/es). */
trait SpanishMessages
{
    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'email' => 'Escribe un correo válido.',
            'max' => 'El campo :attribute es demasiado largo (máximo :max).',
            'min' => 'El campo :attribute debe ser al menos :min.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'numeric' => 'El campo :attribute debe ser un número.',
            'date' => 'El campo :attribute debe ser una fecha válida.',
            'in' => 'Selecciona un valor válido para :attribute.',
            'regex' => 'El formato de :attribute no es válido.',
            'confirmed' => 'La confirmación de :attribute no coincide.',
            'email.unique' => 'Ya existe un usuario con este correo en el gimnasio.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'phone.regex' => 'Escribe un teléfono válido (solo números, de 7 a 20 dígitos).',
            'exists' => 'El :attribute seleccionado no existe en este gimnasio.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'correo',
            'phone' => 'teléfono',
            'plan_name' => 'plan',
            'expires_on' => 'vigencia',
            'credits' => 'créditos',
            'amount' => 'monto',
            'concept' => 'concepto',
            'method' => 'forma de pago',
            'months_added' => 'meses',
            'credits_added' => 'créditos',
            'password' => 'contraseña',
            'payload' => 'código',
            'user_id' => 'socio',
            'q' => 'búsqueda',
        ];
    }
}
