<?php

namespace Tests\Feature\Progress;

use App\Models\Gym;
use App\Models\MemberGoal;
use App\Models\User;
use App\Services\Progress\StreakService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StreakServiceTest extends TestCase
{
    use ProgressTestHelpers, RefreshDatabase;

    private Gym $gym;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        config(['gympro.streaks.weekly_goal' => 3]);
        $this->gym = Gym::factory()->create(['slug' => 'irondemo']);
        $this->member = $this->memberOf($this->gym);
        $this->tenant()->set($this->gym);
    }

    private function streak(): array
    {
        return app(StreakService::class)->compute($this->member);
    }

    public function test_counts_distinct_days_of_current_week_and_marks_today(): void
    {
        // Miércoles 18 nov 2026
        $this->travelTo('2026-11-18 10:00:00');
        $this->scans($this->gym, $this->member,
            '2026-11-16 07:00:00', '2026-11-16 19:30:00', '2026-11-16 20:00:00', // lunes: 3 escaneos = 1 día
            '2026-11-18 06:00:00',
            '2026-11-15 09:00:00', // domingo anterior: otra semana
        );

        $s = $this->streak();

        $this->assertSame('2026-11-16', $s['weekStart']);
        $this->assertSame(2, $s['attendedThisWeek']);
        $this->assertSame(['L', 'M', 'X', 'J', 'V', 'S', 'D'], array_column($s['days'], 'label'));
        $this->assertSame([true, false, true, false, false, false, false], array_column($s['days'], 'attended'));
        $this->assertTrue($s['days'][2]['isToday']);
        $this->assertTrue($s['days'][3]['isFuture']);
        $this->assertSame(1, $s['remaining']);
        $this->assertSame('one_left', $s['status']);
        $this->assertStringContainsString('Te falta 1 día', $s['message']);
    }

    public function test_streak_counts_consecutive_weeks_back_from_last_week(): void
    {
        $this->travelTo('2026-11-18 10:00:00');
        $this->fullWeek($this->gym, $this->member, '2026-10-19', 1); // no cumplida: corta
        foreach (['2026-10-26', '2026-11-02', '2026-11-09'] as $monday) {
            $this->fullWeek($this->gym, $this->member, $monday);
        }
        $this->scans($this->gym, $this->member, '2026-11-17 08:00:00'); // semana actual incompleta

        $s = $this->streak();

        // La semana actual incompleta no suma ni rompe
        $this->assertSame(3, $s['streakWeeks']);
        $this->assertFalse($s['goalMet']);
        $this->assertSame(1, $s['attendedThisWeek']);
        $this->assertSame('in_progress', $s['status']);
    }

    public function test_current_week_adds_once_goal_is_met(): void
    {
        $this->travelTo('2026-11-20 18:00:00'); // viernes
        $this->fullWeek($this->gym, $this->member, '2026-11-09');
        $this->fullWeek($this->gym, $this->member, '2026-11-16'); // lun, mié, vie

        $s = $this->streak();

        $this->assertTrue($s['goalMet']);
        $this->assertSame(2, $s['streakWeeks']);
        $this->assertSame('goal_met', $s['status']);
        $this->assertStringContainsString('Meta cumplida', $s['message']);
    }

    public function test_week_boundary_between_sunday_and_monday(): void
    {
        // Dos semanas cumplidas; la segunda se completa el domingo en la noche
        $this->fullWeek($this->gym, $this->member, '2026-11-02');
        $this->scans($this->gym, $this->member, '2026-11-12 08:00:00', '2026-11-14 08:00:00', '2026-11-15 23:30:00');

        // Domingo 23:59: la semana actual (9–15 nov) ya cumplió
        $this->travelTo('2026-11-15 23:59:00');
        $sunday = $this->streak();
        $this->assertSame('2026-11-09', $sunday['weekStart']);
        $this->assertSame(3, $sunday['attendedThisWeek']);
        $this->assertTrue($sunday['days'][6]['isToday']);
        $this->assertSame(2, $sunday['streakWeeks']);

        // Lunes 00:01: semana nueva en ceros, la racha se conserva
        $this->travelTo('2026-11-16 00:01:00');
        $monday = $this->streak();
        $this->assertSame('2026-11-16', $monday['weekStart']);
        $this->assertSame(0, $monday['attendedThisWeek']);
        $this->assertSame(2, $monday['streakWeeks']);
        $this->assertSame('not_started', $monday['status']);
        $this->assertStringContainsString('no romper la racha', $monday['message']);
    }

    public function test_lost_streak_and_new_streak_messages(): void
    {
        $this->travelTo('2026-11-17 10:00:00'); // martes
        $this->fullWeek($this->gym, $this->member, '2026-10-26');
        $this->fullWeek($this->gym, $this->member, '2026-11-02');
        // semana del 9 nov: nada → racha perdida

        $lost = $this->streak();
        $this->assertSame(0, $lost['streakWeeks']);
        $this->assertSame('streak_lost', $lost['status']);
        $this->assertStringContainsString('2 semanas', $lost['message']);
        $this->assertSame(2, $lost['bestStreakWeeks']);

        $this->scans($this->gym, $this->member, '2026-11-16 08:00:00', '2026-11-17 08:00:00', '2026-11-17 09:00:00');
        $this->travelTo('2026-11-18 10:00:00');
        $this->scans($this->gym, $this->member, '2026-11-18 08:00:00');

        $new = $this->streak();
        $this->assertSame(1, $new['streakWeeks']);
        $this->assertSame('new_streak', $new['status']);
    }

    public function test_out_of_reach_when_not_enough_days_left(): void
    {
        $this->travelTo('2026-11-22 10:00:00'); // domingo
        $this->scans($this->gym, $this->member, '2026-11-20 08:00:00');

        $s = $this->streak();
        $this->assertSame(1, $s['daysLeft']);
        $this->assertSame('out_of_reach', $s['status']);
    }

    public function test_custom_goal_overrides_default(): void
    {
        $this->travelTo('2026-11-18 10:00:00');
        $this->tenant()->runAs($this->gym, function () {
            $goal = new MemberGoal(['weekly_attendance_goal' => 2]);
            $goal->user_id = $this->member->id;
            $goal->save();
        });
        $this->fullWeek($this->gym, $this->member, '2026-11-09', 2);
        $this->scans($this->gym, $this->member, '2026-11-16 08:00:00', '2026-11-18 08:00:00');

        $s = $this->streak();

        $this->assertSame(2, $s['goal']);
        $this->assertTrue($s['goalIsCustom']);
        $this->assertTrue($s['goalMet']);
        $this->assertSame(2, $s['streakWeeks']);
    }

    public function test_result_is_cached_per_gym_and_member(): void
    {
        $this->travelTo('2026-11-18 10:00:00');
        $service = app(StreakService::class);

        $this->assertSame(0, $service->forMember($this->member)['attendedThisWeek']);
        $this->assertTrue(Cache::has("gym:{$this->gym->id}:member:{$this->member->id}:streak"));

        $this->scans($this->gym, $this->member, '2026-11-18 08:00:00');
        $this->assertSame(0, $service->forMember($this->member)['attendedThisWeek'], 'Sigue en caché');

        $service->forget($this->member);
        $this->assertSame(1, $service->forMember($this->member)['attendedThisWeek']);
    }

    public function test_ignores_attendance_of_other_gyms(): void
    {
        $this->travelTo('2026-11-18 10:00:00');
        $this->tenant()->forget();
        $other = Gym::factory()->create();
        $this->tenant()->set($this->gym);
        $this->scans($this->gym, $this->member, '2026-11-16 08:00:00');
        // Fila "colada" de otro gimnasio con el mismo user_id
        $this->scans($other, $this->member, '2026-11-17 08:00:00');

        $this->assertSame(1, $this->streak()['attendedThisWeek']);
    }
}
