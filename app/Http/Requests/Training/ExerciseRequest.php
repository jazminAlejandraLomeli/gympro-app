<?php

namespace App\Http\Requests\Training;

use App\Models\Exercise;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Alta y edición de ejercicios del catálogo. */
class ExerciseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-training');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'muscle_group' => ['required', 'string', Rule::in(array_keys(Exercise::MUSCLE_GROUPS))],
            'instructions' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'muscle_group' => 'grupo muscular',
            'instructions' => 'indicaciones',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Escribe el nombre del ejercicio.',
            'muscle_group.required' => 'Elige el grupo muscular.',
            'muscle_group.in' => 'Elige un grupo muscular de la lista.',
        ];
    }
}
