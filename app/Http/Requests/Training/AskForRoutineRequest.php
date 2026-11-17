<?php

namespace App\Http\Requests\Training;

use App\Models\Routine;
use App\Models\RoutineRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** El socio pide una rutina a los coaches (máximo una pendiente). */
class AskForRoutineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('use-member-portal');
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', 'string', Rule::in(array_keys(Routine::TYPES))],
            'goal' => ['required', 'string', 'min:3', 'max:500'],
            'days_per_week' => ['nullable', 'integer', 'between:1,7'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (RoutineRequest::pending()->where('user_id', $this->user()->id)->exists()) {
                    $validator->errors()->add('request', 'Ya tienes una solicitud pendiente. Espera a que tu coach la atienda o cancélala.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'kind.required' => 'Elige si quieres una rutina de un día o de la semana.',
            'kind.in' => 'Elige si quieres una rutina de un día o de la semana.',
            'goal.required' => 'Cuéntale a tu coach qué quieres lograr.',
            'goal.min' => 'Cuéntale a tu coach un poco más de lo que quieres lograr.',
            'goal.max' => 'Escribe máximo 500 caracteres.',
            'days_per_week.between' => 'Elige entre 1 y 7 días por semana.',
        ];
    }
}
