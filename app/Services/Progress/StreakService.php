<?php

namespace App\Services\Progress;

use App\Models\Attendance;
use App\Models\MemberGoal;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Rachas de asistencia (Módulo C).
 *
 *  - Semana = lunes a domingo, en la zona horaria de la app.
 *  - Varios escaneos el mismo día cuentan como un solo día.
 *  - Racha = semanas consecutivas cumpliendo la meta, contando hacia
 *    atrás desde la semana pasada; la semana actual suma solo si ya
 *    se cumplió (así la racha no "se cae" un lunes por la mañana).
 *  - Una sola consulta indexada (gym_id, user_id, scanned_at) del
 *    último año; el resultado se guarda en Redis unos minutos porque
 *    no podemos escuchar los escaneos (Attendance es compartida).
 */
class StreakService
{
    public const CACHE_SECONDS = 300;

    /** Semanas hacia atrás que se revisan (además de la actual). */
    public const LOOKBACK_WEEKS = 52;

    private const DAY_LABELS = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];

    public function __construct(private TenantContext $tenant) {}

    /** Resumen para la tarjeta de racha (cacheado). */
    public function forMember(User $user): array
    {
        return Cache::remember(
            $this->cacheKey($user),
            self::CACHE_SECONDS,
            fn () => $this->compute($user),
        );
    }

    /** Invalida la racha cacheada (p. ej. al escanear o cambiar la meta). */
    public function forget(User $user): void
    {
        Cache::forget($this->cacheKey($user));
    }

    public function cacheKey(User $user): string
    {
        return $this->tenant->key('member', $user->id, 'streak');
    }

    public function goalFor(User $user): array
    {
        $custom = MemberGoal::query()->where('user_id', $user->id)->value('weekly_attendance_goal');

        return [
            'goal' => (int) ($custom ?? config('gympro.streaks.weekly_goal', 3)),
            'custom' => $custom !== null,
        ];
    }

    /** Cálculo sin caché. */
    public function compute(User $user): array
    {
        $now = CarbonImmutable::now(config('app.timezone'));
        $today = $now->startOfDay();
        $weekStart = $today->startOfWeek(CarbonImmutable::MONDAY);
        $from = $weekStart->subWeeks(self::LOOKBACK_WEEKS);

        ['goal' => $goal, 'custom' => $customGoal] = $this->goalFor($user);

        // Una sola consulta: días distintos con asistencia desde hace un año.
        // scanned_at se guarda en la zona horaria de la app, así que DATE()
        // ya es el día local (MySQL y SQLite).
        $days = Attendance::query()
            ->where('user_id', $user->id)
            ->where('scanned_at', '>=', $from->format('Y-m-d H:i:s'))
            ->where('scanned_at', '<', $weekStart->addWeek()->format('Y-m-d H:i:s'))
            ->selectRaw('DATE(scanned_at) as day')
            ->distinct()
            ->pluck('day')
            ->map(fn ($d) => substr((string) $d, 0, 10))
            ->flip();

        // Días por semana (llave = lunes de esa semana)
        $perWeek = [];
        foreach ($days->keys() as $day) {
            $monday = CarbonImmutable::parse($day, config('app.timezone'))->startOfWeek(CarbonImmutable::MONDAY)->toDateString();
            $perWeek[$monday] = ($perWeek[$monday] ?? 0) + 1;
        }
        $countFor = fn (CarbonImmutable $monday) => $perWeek[$monday->toDateString()] ?? 0;

        // Semana actual
        $calendar = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->addDays($i);
            $calendar[] = [
                'date' => $date->toDateString(),
                'label' => self::DAY_LABELS[$i],
                'attended' => $days->has($date->toDateString()),
                'isToday' => $date->equalTo($today),
                'isFuture' => $date->greaterThan($today),
            ];
        }

        $attended = $countFor($weekStart);
        $goalMet = $attended >= $goal;
        $remaining = max(0, $goal - $attended);
        $attendedToday = $days->has($today->toDateString());
        // Días que aún puede venir esta semana (hoy cuenta si no ha venido)
        $daysLeft = (int) $today->diffInDays($weekStart->addDays(6)) + ($attendedToday ? 0 : 1);

        // Racha: semanas completas hacia atrás desde la semana pasada
        $previous = 0;
        for ($w = $weekStart->subWeek(); $w->greaterThanOrEqualTo($from) && $countFor($w) >= $goal; $w = $w->subWeek()) {
            $previous++;
        }

        // Si la semana pasada se falló: ¿cuánto medía la racha que se perdió?
        $lost = 0;
        if ($previous === 0) {
            for ($w = $weekStart->subWeeks(2); $w->greaterThanOrEqualTo($from) && $countFor($w) >= $goal; $w = $w->subWeek()) {
                $lost++;
            }
        }

        $streak = $previous + ($goalMet ? 1 : 0);

        // Mejor racha del último año
        $best = 0;
        $run = 0;
        for ($w = $from; $w->lessThanOrEqualTo($weekStart); $w = $w->addWeek()) {
            $met = $countFor($w) >= $goal;
            if ($w->equalTo($weekStart) && ! $met) {
                break; // la semana en curso no rompe nada todavía
            }
            $run = $met ? $run + 1 : 0;
            $best = max($best, $run);
        }

        [$status, $message] = $this->message(
            attended: $attended,
            goal: $goal,
            goalMet: $goalMet,
            remaining: $remaining,
            daysLeft: $daysLeft,
            previous: $previous,
            streak: $streak,
            lost: $lost,
        );

        return [
            'weekStart' => $weekStart->toDateString(),
            'today' => $today->toDateString(),
            'days' => $calendar,
            'attendedThisWeek' => $attended,
            'goal' => $goal,
            'goalIsCustom' => $customGoal,
            'goalMet' => $goalMet,
            'remaining' => $remaining,
            'daysLeft' => $daysLeft,
            'streakWeeks' => $streak,
            'bestStreakWeeks' => max($best, $streak),
            'status' => $status,
            'message' => $message,
        ];
    }

    /**
     * Mensaje motivacional según el estado de la semana.
     *
     * @return array{0: string, 1: string}
     */
    private function message(
        int $attended, int $goal, bool $goalMet, int $remaining, int $daysLeft,
        int $previous, int $streak, int $lost,
    ): array {
        if ($goalMet) {
            if ($previous === 0) {
                return ['new_streak', $lost > 0
                    ? '¡Nueva racha en marcha! Cumpliste tu meta y volviste al ritmo.'
                    : '¡Nueva racha en marcha! Cumpliste tu meta de la semana.'];
            }

            return ['goal_met', '¡Meta cumplida! Ya son '.self::weeks($streak).' seguidas. ¡Sigue así!'];
        }

        if ($remaining > $daysLeft) {
            return ['out_of_reach', 'Esta semana ya no alcanzas la meta, pero cada visita suma. ¡El lunes arrancas de nuevo!'];
        }

        if ($remaining === 1) {
            return ['one_left', $previous > 0
                ? '¡Te falta 1 día para cumplir tu meta y llegar a '.self::weeks($previous + 1).' de racha!'
                : '¡Te falta 1 día para cumplir tu meta de la semana!'];
        }

        if ($previous === 0 && $lost > 0) {
            return ['streak_lost', 'Tu racha de '.self::weeks($lost).' terminó, pero hoy es buen día para empezar otra. ¡Tú puedes!'];
        }

        if ($attended === 0) {
            return ['not_started', $previous > 0
                ? 'Llevas '.self::weeks($previous).' seguidas. ¡Ven esta semana para no romper la racha!'
                : '¡Tu primera visita de la semana es el primer paso para una racha!'];
        }

        return ['in_progress', "Vas {$attended} de {$goal}. ¡Te faltan {$remaining} días para tu meta!"];
    }

    private static function weeks(int $n): string
    {
        return $n === 1 ? '1 semana' : "{$n} semanas";
    }
}
