<?php

namespace App\Http\Requests\Training;

use App\Models\Routine;
use App\Tenancy\TenantContext;
use Illuminate\Validation\Rule;

/**
 * Reglas compartidas del constructor (panel y portal del socio):
 * nombre, tipo, objetivo, notas y la lista de ejercicios.
 */
trait ValidatesRoutineItems
{
    public const MAX_ITEMS = 140; // 20 ejercicios × 7 días

    /** Al editar, el tipo es el de la rutina guardada (no se cambia). */
    protected function lockTypeToRoutine(): void
    {
        $routine = $this->route('routine');

        if ($routine instanceof Routine) {
            $this->merge(['type' => $routine->type]);
        } elseif (! $this->filled('type')) {
            $this->merge(['type' => Routine::TYPE_WEEKLY]);
        }
    }

    protected function routineRules(): array
    {
        $gymId = app(TenantContext::class)->id();

        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'string', Rule::in(array_keys(Routine::TYPES))],
            'goal' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],

            'items' => ['nullable', 'array', 'max:'.self::MAX_ITEMS],
            // En las de un día se ignora el día (queda NULL)
            'items.*.day_of_week' => ['nullable', 'required_if:type,'.Routine::TYPE_WEEKLY, 'integer', 'between:1,7'],
            // exists NO pasa por el TenantScope: se filtra a mano por gimnasio
            'items.*.exercise_id' => ['required', 'integer', Rule::exists('exercises', 'id')->where('gym_id', $gymId)],
            'items.*.sets' => ['required', 'integer', 'between:1,20'],
            'items.*.reps' => ['required', 'string', 'max:20'],
            'items.*.weight_kg' => ['nullable', 'numeric', 'between:0,999.99'],
            'items.*.rest_seconds' => ['nullable', 'integer', 'between:0,900'],
            'items.*.notes' => ['nullable', 'string', 'max:200'],
        ];
    }

    protected function routineAttributes(): array
    {
        return [
            'name' => 'nombre',
            'type' => 'tipo',
            'goal' => 'objetivo',
            'notes' => 'notas',
            'items.*.exercise_id' => 'ejercicio',
            'items.*.day_of_week' => 'día',
            'items.*.sets' => 'series',
            'items.*.reps' => 'repeticiones',
            'items.*.weight_kg' => 'peso',
            'items.*.rest_seconds' => 'descanso',
            'items.*.notes' => 'nota',
        ];
    }

    protected function routineMessages(): array
    {
        return [
            'name.required' => 'Escribe el nombre de la rutina.',
            'type.required' => 'Elige si es de un solo día o de la semana completa.',
            'type.in' => 'Elige si es de un solo día o de la semana completa.',
            'items.max' => 'La rutina tiene demasiados ejercicios (máximo '.self::MAX_ITEMS.').',
            'items.*.day_of_week.required_if' => 'Falta el día de la semana de este ejercicio.',
            'items.*.day_of_week.between' => 'El día de la semana no es válido.',
            'items.*.exercise_id.exists' => 'Uno de los ejercicios ya no existe en la biblioteca.',
            'items.*.sets.between' => 'Las series deben ser entre 1 y 20.',
            'items.*.reps.required' => 'Escribe las repeticiones (p. ej. 10-12).',
            'items.*.weight_kg.between' => 'El peso debe ser entre 0 y 999 kg.',
            'items.*.rest_seconds.between' => 'El descanso debe ser entre 0 y 900 segundos.',
            'items.*.notes.max' => 'La nota del ejercicio debe tener máximo 200 caracteres.',
        ];
    }
}
