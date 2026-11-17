<?php

namespace App\Http\Requests\Membership;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    use SpanishMessages;

    public function authorize(): bool
    {
        return $this->user()->can('operate-front-desk');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        $gymId = app(TenantContext::class)->id();

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required', 'email', 'max:190',
                Rule::unique('users', 'email')->where('gym_id', $gymId)->ignore($this->route('membership')->user_id),
            ],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'plan_name' => ['nullable', 'string', 'max:80'],
            // Ajustes manuales (correcciones de recepción)
            'expires_on' => ['nullable', 'date'],
            'credits' => ['required', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
