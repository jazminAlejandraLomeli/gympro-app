<?php

namespace App\Http\Requests\Membership;

use App\Models\MemberPayment;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMemberRequest extends FormRequest
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
            // Único POR gimnasio (las reglas unique no pasan por el Global Scope)
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->where('gym_id', $gymId)],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'plan_name' => ['nullable', 'string', 'max:80'],
            // Pago inicial (opcional)
            'months_added' => ['nullable', 'integer', 'min:0', 'max:24'],
            'credits_added' => ['nullable', 'integer', 'min:0', 'max:500'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'method' => ['nullable', Rule::in(array_keys(MemberPayment::METHODS))],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $renews = (int) $this->input('months_added') > 0 || (int) $this->input('credits_added') > 0;

                if ($renews && ! $this->filled('method')) {
                    $validator->errors()->add('method', 'Selecciona la forma de pago.');
                }
            },
        ];
    }
}
