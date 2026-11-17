<?php

namespace App\Http\Requests\Training;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** "Asignar existente": atiende una solicitud con una plantilla del gimnasio. */
class FulfillWithTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-training');
    }

    public function rules(): array
    {
        return [
            // exists NO pasa por el TenantScope: gimnasio a mano, y solo plantillas
            'template_id' => [
                'required',
                'integer',
                Rule::exists('routines', 'id')->where('gym_id', app(TenantContext::class)->id())->where('is_template', true),
            ],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'template_id.required' => 'Elige una plantilla.',
            'template_id.exists' => 'Esa plantilla no existe en este gimnasio.',
            'date.date_format' => 'La fecha no es válida.',
        ];
    }
}
