<?php

namespace App\Http\Requests\Training;

use App\Models\Exercise;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Alta y edición de ejercicios de la biblioteca. */
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
            'objective' => ['nullable', 'string', 'max:150'],
            'equipment' => ['nullable', 'string', Rule::in(array_keys(Exercise::EQUIPMENT))],
            'difficulty' => ['nullable', 'string', Rule::in(array_keys(Exercise::DIFFICULTIES))],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'muscle_group' => 'grupo muscular',
            'objective' => 'objetivo',
            'equipment' => 'equipo',
            'difficulty' => 'dificultad',
            'instructions' => 'cómo se hace',
            'notes' => 'notas',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Escribe el nombre del ejercicio.',
            'muscle_group.required' => 'Elige el grupo muscular.',
            'muscle_group.in' => 'Elige un grupo muscular de la lista.',
            'equipment.in' => 'Elige un equipo de la lista.',
            'difficulty.in' => 'Elige una dificultad de la lista.',
            'objective.max' => 'El objetivo debe tener máximo 150 caracteres.',
        ];
    }
}
