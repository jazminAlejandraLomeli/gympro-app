<?php

namespace App\Services\Training;

use App\Models\Routine;
use App\Models\RoutineItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Guarda una rutina completa (datos + ejercicios) en una sola
 * transacción: reemplaza todos los renglones. La posición sale del
 * orden en que llegan los ejercicios de cada día. En las rutinas de un
 * solo día, day_of_week queda en NULL.
 */
class RoutineWriter
{
    /**
     * @param  array{name?: string, type?: string, goal?: ?string, notes?: ?string}  $attributes
     * @param  null|list<array{day_of_week?: ?int, exercise_id: int, sets: int, reps: string, weight_kg?: ?float, rest_seconds?: ?int, notes?: ?string}>  $items  null = no tocar los ejercicios
     */
    public function save(Routine $routine, array $attributes, ?array $items): Routine
    {
        return DB::transaction(function () use ($routine, $attributes, $items) {
            $routine->fill($attributes)->save();

            if ($items === null) {
                return $routine;
            }

            RoutineItem::where('routine_id', $routine->id)->delete();

            $single = $routine->isSingle();
            $positions = [];
            $rows = [];
            foreach ($items as $item) {
                $day = $single ? null : (int) $item['day_of_week'];
                $slot = $day ?? 0;
                $positions[$slot] = ($positions[$slot] ?? -1) + 1;

                $notes = trim((string) ($item['notes'] ?? ''));

                $rows[] = [
                    'day_of_week' => $day,
                    'exercise_id' => (int) $item['exercise_id'],
                    'position' => $positions[$slot],
                    'sets' => (int) $item['sets'],
                    'reps' => trim((string) $item['reps']),
                    'weight_kg' => $item['weight_kg'] ?? null,
                    'rest_seconds' => $item['rest_seconds'] ?? null,
                    'notes' => $notes === '' ? null : $notes,
                ];
            }

            // createMany pasa por BelongsToGym (gym_id del gimnasio activo)
            $routine->items()->createMany($rows);

            return $routine->refresh();
        });
    }

    /**
     * Copia una rutina con todos sus ejercicios.
     *
     *  - $member = socio → rutina personal de ese socio.
     *  - $member = null  → otra plantilla del gimnasio.
     */
    public function duplicate(Routine $source, ?User $member, ?User $createdBy, ?string $name = null): Routine
    {
        return DB::transaction(function () use ($source, $member, $createdBy, $name) {
            $copy = new Routine([
                'name' => mb_substr($name ?? $source->name, 0, 100),
                'type' => $source->type,
                'goal' => $source->goal,
                'notes' => $source->notes,
            ]);
            $copy->forceFill([
                'is_template' => $member === null,
                'member_id' => $member?->id,
                'created_by' => $createdBy?->id,
            ])->save();

            $rows = $source->items()->get()->map(fn (RoutineItem $item) => $item->only([
                'day_of_week', 'exercise_id', 'position', 'sets', 'reps', 'weight_kg', 'rest_seconds', 'notes',
            ]))->all();

            if ($rows !== []) {
                $copy->items()->createMany($rows);
            }

            return $copy;
        });
    }
}
