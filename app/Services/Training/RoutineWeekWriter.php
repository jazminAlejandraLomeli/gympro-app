<?php

namespace App\Services\Training;

use App\Models\Routine;
use App\Models\RoutineItem;
use Illuminate\Support\Facades\DB;

/**
 * Guarda la semana completa de una rutina en una sola transacción:
 * reemplaza todos los renglones. La posición sale del orden en que
 * llegan los ejercicios de cada día.
 */
class RoutineWeekWriter
{
    /**
     * @param  array{name: string, goal?: ?string, notes?: ?string}  $attributes
     * @param  null|list<array{day_of_week: int, exercise_id: int, sets: int, reps: string, weight_kg?: ?float, rest_seconds?: ?int}>  $items  null = no tocar los ejercicios
     */
    public function save(Routine $routine, array $attributes, ?array $items): Routine
    {
        return DB::transaction(function () use ($routine, $attributes, $items) {
            $routine->fill($attributes)->save();

            if ($items === null) {
                return $routine;
            }

            RoutineItem::where('routine_id', $routine->id)->delete();

            $positions = [];
            $rows = [];
            foreach ($items as $item) {
                $day = (int) $item['day_of_week'];
                $positions[$day] = ($positions[$day] ?? -1) + 1;

                $rows[] = [
                    'day_of_week' => $day,
                    'exercise_id' => (int) $item['exercise_id'],
                    'position' => $positions[$day],
                    'sets' => (int) $item['sets'],
                    'reps' => trim((string) $item['reps']),
                    'weight_kg' => $item['weight_kg'] ?? null,
                    'rest_seconds' => $item['rest_seconds'] ?? null,
                ];
            }

            // createMany pasa por BelongsToGym (gym_id del gimnasio activo)
            $routine->items()->createMany($rows);

            return $routine->refresh();
        });
    }
}
