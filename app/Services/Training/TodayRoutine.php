<?php

namespace App\Services\Training;

use App\Models\Routine;
use App\Models\RoutineAssignment;
use App\Models\RoutineItem;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Rutina activa del socio, lista para el navegador.
 *
 *  - forMemberHome(): props de <TodayRoutineCard /> en el inicio del socio.
 *  - forRoutinePage(): semana completa para la pantalla "Rutina".
 */
class TodayRoutine
{
    public const TIMEZONE = 'America/Mexico_City';

    public const ROUTINE_URL = '/app/rutina';

    public function activeAssignment(User $user): ?RoutineAssignment
    {
        return RoutineAssignment::query()
            ->active()
            ->where('user_id', $user->id)
            ->with(['routine.items.exercise'])
            ->latest('id')
            ->first();
    }

    /** Día ISO de hoy en la zona del gimnasio (1 = lunes … 7 = domingo). */
    public function today(): int
    {
        return CarbonImmutable::now(self::TIMEZONE)->dayOfWeekIso;
    }

    /**
     * Props para resources/js/Components/Member/TodayRoutineCard.jsx
     *
     * @return array{hasRoutine: bool, routineName: ?string, day: int, dayName: string, isRestDay: bool, exercises: list<array>, href: string}
     */
    public function forMemberHome(User $user): array
    {
        $today = $this->today();
        $routine = $this->activeAssignment($user)?->routine;

        $exercises = $routine
            ? $routine->items->where('day_of_week', $today)->values()->map(fn (RoutineItem $item) => [
                'id' => $item->id,
                'name' => $item->exercise->name,
                'muscle_group_label' => $item->exercise->muscleGroupLabel(),
                'sets' => $item->sets,
                'reps' => $item->reps,
                'weight_kg' => $item->weight_kg !== null ? (float) $item->weight_kg : null,
            ])->all()
            : [];

        return [
            'hasRoutine' => $routine !== null,
            'routineName' => $routine?->name,
            'day' => $today,
            'dayName' => Routine::DAY_NAMES[$today],
            'isRestDay' => $routine !== null && $exercises === [],
            'exercises' => $exercises,
            'href' => self::ROUTINE_URL,
        ];
    }

    /** Semana completa para Pages/Member/Routine.jsx */
    public function forRoutinePage(User $user): array
    {
        $assignment = $this->activeAssignment($user);
        $routine = $assignment?->routine;

        if (! $routine) {
            return ['routine' => null, 'today' => $this->today(), 'days' => []];
        }

        $days = [];
        foreach (Routine::DAY_NAMES as $day => $name) {
            $days[] = [
                'day' => $day,
                'short' => Routine::DAY_SHORT[$day],
                'name' => $name,
                'items' => $routine->items->where('day_of_week', $day)->values()
                    ->map(fn (RoutineItem $item) => self::itemToArray($item))->all(),
            ];
        }

        return [
            'routine' => [
                'id' => $routine->id,
                'name' => $routine->name,
                'goal' => $routine->goal,
                'notes' => $routine->notes,
                'starts_on' => $assignment->starts_on->toDateString(),
            ],
            'today' => $this->today(),
            'days' => $days,
        ];
    }

    public static function itemToArray(RoutineItem $item): array
    {
        return [
            'id' => $item->id,
            'day_of_week' => $item->day_of_week,
            'position' => $item->position,
            'sets' => $item->sets,
            'reps' => $item->reps,
            'weight_kg' => $item->weight_kg !== null ? (float) $item->weight_kg : null,
            'rest_seconds' => $item->rest_seconds,
            'exercise' => $item->exercise->toClientArray(),
        ];
    }
}
