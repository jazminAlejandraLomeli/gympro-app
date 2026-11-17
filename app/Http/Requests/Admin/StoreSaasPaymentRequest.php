<?php

namespace App\Http\Requests\Admin;

use App\Models\SaasPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Registro manual de un pago de licencia SaaS. */
class StoreSaasPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('access-saas-admin');
    }

    public function rules(): array
    {
        return [
            'months' => ['required', 'integer', 'min:1', 'max:24'],
            'amount' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'paid_at' => ['nullable', 'date', 'before_or_equal:today', 'after:2000-01-01'],
            'method' => ['required', 'string', Rule::in(array_keys(SaasPayment::METHODS))],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Este campo es obligatorio.',
            'months.integer' => 'Escribe un número de meses.',
            'months.min' => 'Al menos 1 mes.',
            'months.max' => 'Máximo 24 meses por pago.',
            'amount.numeric' => 'Escribe una cantidad válida.',
            'amount.min' => 'El monto no puede ser negativo.',
            'paid_at.date' => 'Fecha no válida.',
            'paid_at.before_or_equal' => 'La fecha de pago no puede ser futura.',
            'method.in' => 'Elige un método de pago válido.',
            'notes.max' => 'Las notas pueden tener hasta 500 caracteres.',
        ];
    }
}
