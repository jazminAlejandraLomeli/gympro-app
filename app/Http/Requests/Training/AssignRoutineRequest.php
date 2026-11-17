<?php

namespace App\Http\Requests\Training;

use App\Enums\Role;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Asigna una rutina a uno o varios socios del gimnasio activo. */
class AssignRoutineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-training');
    }

    public function rules(): array
    {
        $gymId = app(TenantContext::class)->id();

        return [
            'user_ids' => ['required', 'array', 'min:1', 'max:200'],
            // exists NO pasa por el TenantScope: gimnasio y rol a mano
            'user_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where('gym_id', $gymId)->where('role', Role::Member->value),
            ],
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_ids.required' => 'Elige al menos un socio.',
            'user_ids.min' => 'Elige al menos un socio.',
            'user_ids.*.exists' => 'Uno de los socios elegidos no pertenece a este gimnasio.',
            'starts_on.date_format' => 'La fecha de inicio no es válida.',
        ];
    }
}
