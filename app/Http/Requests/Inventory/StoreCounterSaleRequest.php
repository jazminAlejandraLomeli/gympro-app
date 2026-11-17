<?php

namespace App\Http\Requests\Inventory;

use App\Enums\Role;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cobro en mostrador: renglones del carrito y socio opcional. */
class StoreCounterSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('operate-front-desk');
    }

    public function rules(): array
    {
        // exists no pasa por el TenantScope: se filtra por gimnasio a mano
        $gymId = app(TenantContext::class)->id();

        return [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('gym_id', $gymId)],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],
            'member_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('gym_id', $gymId)->where('role', Role::Member->value),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Agrega al menos un producto al carrito.',
            'items.min' => 'Agrega al menos un producto al carrito.',
            'items.max' => 'El carrito admite hasta 50 renglones.',
            'items.*.product_id.required' => 'Falta el producto en un renglón.',
            'items.*.product_id.distinct' => 'Hay un producto repetido en el carrito.',
            'items.*.product_id.exists' => 'Uno de los productos no existe en este gimnasio.',
            'items.*.quantity.required' => 'Falta la cantidad en un renglón.',
            'items.*.quantity.integer' => 'La cantidad debe ser un número entero.',
            'items.*.quantity.min' => 'La cantidad mínima es 1.',
            'items.*.quantity.max' => 'La cantidad máxima por renglón es 999.',
            'member_id.exists' => 'El socio no existe en este gimnasio.',
        ];
    }
}
