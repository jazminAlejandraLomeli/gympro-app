<?php

namespace App\Http\Requests\Membership;

use App\Models\MemberPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RecordPaymentRequest extends FormRequest
{
    use SpanishMessages;

    public function authorize(): bool
    {
        return $this->user()->can('operate-front-desk');
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0', 'max:999999'],
            'concept' => ['nullable', 'string', 'max:120'],
            'method' => ['required', Rule::in(array_keys(MemberPayment::METHODS))],
            'months_added' => ['nullable', 'integer', 'min:0', 'max:24'],
            'credits_added' => ['nullable', 'integer', 'min:0', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ((int) $this->input('months_added') <= 0 && (int) $this->input('credits_added') <= 0) {
                    $validator->errors()->add('months_added', 'Agrega al menos un mes o un crédito.');
                }
            },
        ];
    }
}
