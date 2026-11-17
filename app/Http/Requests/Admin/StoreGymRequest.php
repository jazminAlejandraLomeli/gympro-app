<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Alta rápida de gimnasio (super_admin). */
class StoreGymRequest extends FormRequest
{
    /** Subdominio válido: minúsculas, números y guiones sueltos; sin guion al inicio/fin. */
    public const SLUG_REGEX = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function authorize(): bool
    {
        return $this->user()->can('access-saas-admin');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => is_string($this->slug) ? trim($this->slug) : $this->slug,
            'admin_email' => is_string($this->admin_email) ? strtolower(trim($this->admin_email)) : $this->admin_email,
        ]);
    }

    public function rules(): array
    {
        $hex = ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'];

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'required', 'string', 'min:3', 'max:63',
                'regex:'.self::SLUG_REGEX,
                Rule::notIn(config('gympro.reserved_subdomains')),
                Rule::unique('gyms', 'slug'),
            ],
            'primary_color' => $hex,
            'secondary_color' => $hex,
            'referred_by_gym_id' => ['nullable', 'integer', Rule::exists('gyms', 'id')],
            'admin_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'string', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'slug' => 'subdominio',
            'primary_color' => 'color principal',
            'secondary_color' => 'color secundario',
            'referred_by_gym_id' => 'referido por',
            'admin_name' => 'nombre del administrador',
            'admin_email' => 'correo del administrador',
            'admin_password' => 'contraseña',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Este campo es obligatorio.',
            'slug.regex' => 'Usa solo minúsculas, números y guiones (sin guion al inicio o al final).',
            'slug.not_in' => 'Ese subdominio está reservado.',
            'slug.unique' => 'Ese subdominio ya lo usa otro gimnasio.',
            'slug.min' => 'El subdominio debe tener al menos 3 caracteres.',
            'slug.max' => 'El subdominio puede tener hasta 63 caracteres.',
            'primary_color.regex' => 'Usa un color en formato #RRGGBB.',
            'secondary_color.regex' => 'Usa un color en formato #RRGGBB.',
            'referred_by_gym_id.exists' => 'Ese gimnasio no existe.',
            'admin_email.email' => 'Ese correo no parece válido.',
            'admin_password.min' => 'La contraseña debe tener al menos 8 caracteres.',
        ];
    }
}
