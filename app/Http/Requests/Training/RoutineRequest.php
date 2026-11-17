<?php

namespace App\Http\Requests\Training;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Crea una rutina o guarda la semana completa en una sola petición.
 * Sin `items` (alta rápida) solo se validan nombre, objetivo y notas.
 */
class RoutineRequest extends FormRequest
{
    public const MAX_ITEMS = 140; // 20 ejercicios × 7 días

    public function authorize(): bool
    {
        return $this->user()->can('manage-training');
    }

    public function rules(): array
    {
        $gymId = app(TenantContext::class)->id();

        return [
            'name' => ['required', 'string', 'max:100'],
            'goal' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'items' => ['sometimes', 'array', 'max:'.self::MAX_ITEMS],
            'items.*.day_of_week' => ['required', 'integer', 'between:1,7'],
            // exists NO pasa por el TenantScope: se filtra a mano por gimnasio
            'items.*.exercise_id' => ['required', 'integer', Rule::exists('exercises', 'id')->where('gym_id', $gymId)],
            'items.*.sets' => ['required', 'integer', 'between:1,20'],
            'items.*.reps' => ['required', 'string', 'max:20'],
            'items.*.weight_kg' => ['nullable', 'numeric', 'between:0,999.99'],
            'items.*.rest_seconds' => ['nullable', 'integer', 'between:0,900'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'goal' => 'objetivo',
            'notes' => 'notas',
            'items.*.exercise_id' => 'ejercicio',
            'items.*.day_of_week' => 'día',
            'items.*.sets' => 'series',
            'items.*.reps' => 'repeticiones',
            'items.*.weight_kg' => 'peso',
            'items.*.rest_seconds' => 'descanso',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Escribe el nombre de la rutina.',
            'items.max' => 'La rutina tiene demasiados ejercicios (máximo '.self::MAX_ITEMS.').',
            'items.*.exercise_id.exists' => 'Uno de los ejercicios ya no existe en el catálogo.',
            'items.*.sets.between' => 'Las series deben ser entre 1 y 20.',
            'items.*.reps.required' => 'Escribe las repeticiones (p. ej. 10-12).',
            'items.*.weight_kg.between' => 'El peso debe ser entre 0 y 999 kg.',
            'items.*.rest_seconds.between' => 'El descanso debe ser entre 0 y 900 segundos.',
        ];
    }
}
