<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Datos SaaS de un gimnasio: nombre, cuota, descuento y referido por. */
class UpdateGymRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('access-saas-admin');
    }

    public function rules(): array
    {
        $gym = $this->route('gym');

        return [
            'name' => ['required', 'string', 'max:120'],
            'saas_monthly_fee' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'saas_discount' => ['required', 'numeric', 'min:0', 'lte:saas_monthly_fee'],
            'referred_by_gym_id' => [
                'nullable', 'integer',
                Rule::exists('gyms', 'id'),
                Rule::notIn([$gym->id]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Este campo es obligatorio.',
            'numeric' => 'Escribe una cantidad válida.',
            'saas_monthly_fee.min' => 'La cuota no puede ser negativa.',
            'saas_discount.min' => 'El descuento no puede ser negativo.',
            'saas_discount.lte' => 'El descuento no puede ser mayor que la cuota.',
            'referred_by_gym_id.exists' => 'Ese gimnasio no existe.',
            'referred_by_gym_id.not_in' => 'Un gimnasio no puede referirse a sí mismo.',
        ];
    }
}
