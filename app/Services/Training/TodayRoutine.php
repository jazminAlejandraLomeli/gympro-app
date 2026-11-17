<?php

namespace App\Services\Training;

use App\Models\Routine;
use App\Models\RoutineAssignment;
use App\Models\RoutineItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Rutina del socio, lista para el navegador.
 *
 * "Hoy" se decide así: si hay una rutina de un día con fecha de hoy,
 * esa; si no, el día de hoy de la semanal activa; si no, nada.
 *
 *  - forMemberHome(): props de <TodayRoutineCard /> en el inicio del socio.
 *  - forRoutinePage(): "Tu rutina de hoy" + la semana completa.
 */
class TodayRoutine
{
    public const TIMEZONE = 'America/Mexico_City';

    public const ROUTINE_URL = '/app/rutina';

    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE);
    }

    /** Fecha de hoy en la zona del gimnasio (Y-m-d). */
    public function todayDate(): string
    {
        return $this->now()->toDateString();
    }

    /** Día ISO de hoy en la zona del gimnasio (1 = lunes … 7 = domingo). */
    public function today(): int
    {
        return $this->now()->dayOfWeekIso;
    }

    /** Semanal activa del socio. */
    public function activeWeekly(User $user): ?RoutineAssignment
    {
        return RoutineAssignment::query()
            ->active()
            ->weekly()
            ->where('user_id', $user->id)
            ->with(['routine.items.exercise', 'routine.creator:id,name'])
            ->latest('id')
            ->first();
    }

    /** Rutina de un día programada para hoy. */
    public function singleForToday(User $user): ?RoutineAssignment
    {
        return RoutineAssignment::query()
            ->active()
            ->where('user_id', $user->id)
            ->where('scheduled_for', $this->todayDate())
            ->with(['routine.items.exercise', 'routine.creator:id,name'])
            ->latest('id')
            ->first();
    }

    /**
     * Props para resources/js/Components/Member/TodayRoutineCard.jsx
     *
     * @return array{hasRoutine: bool, routineName: ?string, day: int, dayName: string, isRestDay: bool, exercises: list<array>, href: string, source: ?string, assignedBy: ?string, createdBySelf: bool}
     */
    public function forMemberHome(User $user): array
    {
        $today = $this->today();
        [$source, $routine, $items] = $this->resolveToday($user);

        $exercises = $items->map(fn (RoutineItem $item) => [
            'id' => $item->id,
            'name' => $item->exercise->name,
            'muscle_group_label' => $item->exercise->muscleGroupLabel(),
            'sets' => $item->sets,
            'reps' => $item->reps,
            'weight_kg' => $item->weight_kg !== null ? (float) $item->weight_kg : null,
        ])->all();

        $author = $routine ? self::author($routine, $user) : null;

        return [
            'hasRoutine' => $routine !== null,
            'routineName' => $routine?->name,
            'day' => $today,
            'dayName' => Routine::DAY_NAMES[$today],
            'isRestDay' => $routine !== null && $exercises === [],
            'exercises' => $exercises,
            'href' => self::ROUTINE_URL,
            // Llaves agregadas (no rompen a quien solo lee las anteriores)
            'source' => $source,
            'assignedBy' => $author && ! $author['isSelf'] ? $author['name'] : null,
            'createdBySelf' => (bool) ($author['isSelf'] ?? false),
        ];
    }

    /** "Tu rutina de hoy" y la semanal activa para Pages/Member/Routine.jsx */
    public function forRoutinePage(User $user): array
    {
        $today = $this->today();
        [$source, $routine, $items] = $this->resolveToday($user);
        $weekly = $this->activeWeekly($user);

        return [
            'day' => $today,
            'dayName' => Routine::DAY_NAMES[$today],
            'todayDate' => $this->todayDate(),
            'today' => $routine ? [
                'source' => $source,
                'routine' => self::summary($routine, $user),
                'items' => $items->map(fn (RoutineItem $item) => self::itemToArray($item))->all(),
                'isRestDay' => $items->isEmpty(),
            ] : null,
            'weekly' => $weekly ? [
                'routine' => self::summary($weekly->routine, $user) + ['starts_on' => $weekly->starts_on->toDateString()],
                'days' => self::days($weekly->routine),
            ] : null,
        ];
    }

    /**
     * @return array{0: ?string, 1: ?Routine, 2: Collection<int, RoutineItem>}
     */
    private function resolveToday(User $user): array
    {
        if ($single = $this->singleForToday($user)) {
            return [Routine::TYPE_SINGLE, $single->routine, $single->routine->items->values()];
        }

        if ($weekly = $this->activeWeekly($user)) {
            $routine = $weekly->routine;

            return [Routine::TYPE_WEEKLY, $routine, $routine->items->where('day_of_week', $this->today())->values()];
        }

        return [null, null, collect()];
    }

    /** Semana L–D con sus ejercicios. */
    public static function days(Routine $routine): array
    {
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

        return $days;
    }

    /** Quién la armó, visto por $viewer: "Coach Ana" o él mismo. */
    public static function author(Routine $routine, ?User $viewer = null): array
    {
        $isSelf = $viewer !== null && $routine->created_by === $viewer->id;

        return [
            'name' => $isSelf ? $viewer->name : ($routine->creator?->name ?? 'Tu gimnasio'),
            'isSelf' => $isSelf,
        ];
    }

    public static function summary(Routine $routine, ?User $viewer = null): array
    {
        return [
            'id' => $routine->id,
            'name' => $routine->name,
            'type' => $routine->type,
            'type_label' => $routine->typeLabel(),
            'goal' => $routine->goal,
            'notes' => $routine->notes,
            'author' => self::author($routine, $viewer),
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
            'notes' => $item->notes,
            'exercise' => $item->exercise->toClientArray(),
        ];
    }
}
