<?php

namespace Database\Seeders\Demo;

use App\Enums\Role;
use App\Models\Attendance;
use App\Models\BodyMeasurement;
use App\Models\Gym;
use App\Models\MemberGoal;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Módulos C y D: asistencias de ~10 semanas, metas y valoraciones.
 * Corre dentro del gimnasio (TenantContext::runAs).
 *
 *  - socio@demo.test: racha de 4 semanas, 2 días esta semana (o los que
 *    quepan si hoy es lunes) y 6 valoraciones mensuales con el peso
 *    bajando; la última hace 35 días para que salga el recordatorio.
 *  - Otros socios: patrones variados (constante, racha perdida, nuevo…).
 */
class ProgressDemoSeeder
{
    private CarbonImmutable $now;

    private CarbonImmutable $weekStart;

    public function seedGym(Gym $gym): void
    {
        $this->now = CarbonImmutable::now(config('app.timezone'));
        $this->weekStart = $this->now->startOfDay()->startOfWeek(CarbonImmutable::MONDAY);

        $members = User::query()->where('role', Role::Member)->orderBy('id')->get();
        $coachId = User::query()->where('role', Role::Trainer)->value('id');

        $demo = $members->firstWhere('email', 'socio@demo.test');
        if ($demo) {
            $this->seedDemoMember($demo, $coachId);
        }

        // Patrones: días por semana de la más antigua (10 semanas atrás) a la pasada
        $patterns = [
            [4, 4, 3, 5, 4, 4, 3, 4, 5, 4],  // constante
            [3, 3, 4, 3, 3, 3, 4, 3, 1, 0],  // perdió la racha hace poco
            [1, 2, 0, 1, 2, 1, 0, 2, 1, 2],  // esporádico
            [0, 0, 0, 0, 0, 0, 3, 3, 4, 3],  // nuevo y motivado
            [2, 2, 2, 2, 2, 2, 2, 2, 2, 2],  // meta personal de 2 días
        ];

        foreach ($members->reject(fn (User $u) => $u->is($demo))->values() as $i => $member) {
            $pattern = $patterns[$i % count($patterns)];
            $this->seedWeeks($member, $pattern, thisWeek: $i % 3);

            if ($i % count($patterns) === 4) {
                $goal = new MemberGoal(['weekly_attendance_goal' => 2]);
                $goal->user_id = $member->id;
                $goal->save();
            }

            // Algunas valoraciones: unos al día, otros pendientes, otros sin ninguna
            match ($i % 3) {
                0 => $this->seedMeasurements($member, $coachId, months: 3, lastDaysAgo: 10, startWeight: 78.0, monthlyChange: -0.8),
                1 => $this->seedMeasurements($member, $coachId, months: 2, lastDaysAgo: 45, startWeight: 65.0, monthlyChange: 0.6),
                default => null,
            };
        }
    }

    private function seedDemoMember(User $member, ?int $coachId): void
    {
        // 5 semanas atrás: 1 día (corta la racha); luego 4 semanas cumpliendo 3+
        $this->seedWeeks($member, [3, 2, 4, 3, 4, 1, 3, 4, 3, 5], thisWeek: 2);

        $this->seedMeasurements($member, $coachId, months: 6, lastDaysAgo: 35, startWeight: 92.0, monthlyChange: -1.6);
    }

    /**
     * @param  array<int, int>  $daysPerWeek  de la semana más antigua a la pasada
     * @param  int  $thisWeek  días a registrar en la semana actual (solo hasta hoy)
     */
    private function seedWeeks(User $member, array $daysPerWeek, int $thisWeek): void
    {
        $weeks = count($daysPerWeek);

        foreach ($daysPerWeek as $offset => $count) {
            $monday = $this->weekStart->subWeeks($weeks - $offset);
            foreach ($this->pickDays($count, 7, $member->id + $offset) as $day) {
                $this->scan($member, $monday->addDays($day)->setTime(6 + ($member->id + $day) % 14, 15 * ($day % 4)));
            }
        }

        // Semana actual: hoy y los días anteriores (nunca a futuro)
        $today = (int) $this->now->dayOfWeekIso - 1; // 0 = lunes
        $days = array_slice(range($today, 0), 0, min($thisWeek, $today + 1));
        foreach ($days as $day) {
            $at = $this->weekStart->addDays($day)->setTime(7, 30);
            if ($at->greaterThan($this->now)) {
                $at = $this->now->subMinutes(20)->max($this->now->startOfDay());
            }
            $this->scan($member, $at);
        }
    }

    /** $count días distintos entre 0 y $span-1, repartidos de forma estable. */
    private function pickDays(int $count, int $span, int $seed): array
    {
        $days = range(0, $span - 1);
        mt_srand($seed);
        shuffle($days);
        mt_srand();
        $picked = array_slice($days, 0, min($count, $span));
        sort($picked);

        return $picked;
    }

    private function scan(User $member, CarbonImmutable $at): void
    {
        $attendance = new Attendance([
            'user_id' => $member->id,
            'scanned_at' => $at,
            'method' => 'qr',
            'credits_used' => 0,
        ]);
        $attendance->created_at = $at;
        $attendance->save();
    }

    private function seedMeasurements(User $member, ?int $coachId, int $months, int $lastDaysAgo, float $startWeight, float $monthlyChange): void
    {
        $losing = $monthlyChange < 0;

        for ($i = 0; $i < $months; $i++) {
            $step = $i;
            $date = $this->now->subDays($lastDaysAgo + 30 * ($months - 1 - $i));

            $m = new BodyMeasurement([
                'measured_on' => $date->toDateString(),
                'weight_kg' => round($startWeight + $monthlyChange * $step, 1),
                'body_fat_pct' => round(($losing ? 30 : 18) + ($losing ? -0.9 : -0.3) * $step, 1),
                'waist_cm' => round(($losing ? 102 : 76) + ($losing ? -1.2 : 0.2) * $step, 1),
                'chest_cm' => round(($losing ? 108 : 90) + ($losing ? -0.5 : 0.8) * $step, 1),
                'arm_cm' => round(($losing ? 34 : 29) + 0.4 * $step, 1),
                'leg_cm' => round(($losing ? 60 : 52) + 0.3 * $step, 1),
                'notes' => $i === 0 ? 'Valoración inicial.' : null,
            ]);
            $m->user_id = $member->id;
            // La primera la toma el entrenador, las siguientes alternan
            $m->recorded_by = ($i % 2 === 0 && $coachId) ? $coachId : $member->id;
            $m->save();
        }
    }
}
