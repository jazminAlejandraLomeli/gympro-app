<?php

namespace App\Http\Requests\Membership;

use App\Enums\Role;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManualCheckInRequest extends FormRequest
{
    use SpanishMessages;

    public function authorize(): bool
    {
        return $this->user()->can('operate-front-desk');
    }

    public function rules(): array
    {
        return [
            // exists NO pasa por el Global Scope: se filtra a mano por gimnasio
            'user_id' => [
                'required', 'integer',
                Rule::exists('users', 'id')
                    ->where('gym_id', app(TenantContext::class)->id())
                    ->where('role', Role::Member->value),
            ],
        ];
    }
}
