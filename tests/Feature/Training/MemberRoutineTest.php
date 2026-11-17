<?php

namespace Tests\Feature\Training;

use App\Enums\Role;
use App\Models\Routine;
use App\Models\RoutineAssignment;
use App\Models\User;
use App\Services\Training\TodayRoutine;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

class MemberRoutineTest extends TrainingTestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function assign(Routine $routine, User $member, bool $active = true): void
    {
        $this->inGym($this->iron, fn () => RoutineAssignment::create([
            'routine_id' => $routine->id, 'user_id' => $member->id, 'starts_on' => '2026-11-16', 'active' => $active,
        ]));
    }

    private function routineWithMonday(string $name, string $exerciseName): Routine
    {
        $routine = $this->routine($this->iron, ['name' => $name]);
        $exercise = $this->exercise($this->iron, ['name' => $exerciseName]);
        $this->inGym($this->iron, fn () => $routine->items()->create([
            'day_of_week' => 1, 'exercise_id' => $exercise->id, 'position' => 0, 'sets' => 4, 'reps' => '10-12', 'weight_kg' => 20, 'rest_seconds' => 60,
        ]));

        return $routine;
    }

    public function test_member_sees_only_his_active_routine_with_today_selected(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-18 10:00', TodayRoutine::TIMEZONE)); // miércoles

        $member = $this->user($this->iron);
        $other = $this->user($this->iron);
        $mine = $this->routineWithMonday('Mía', 'Sentadilla');
        $old = $this->routineWithMonday('Anterior', 'Press');
        $theirs = $this->routineWithMonday('De otro', 'Remo');
        $this->assign($old, $member, active: false);
        $this->assign($mine, $member);
        $this->assign($theirs, $other);

        $this->actingAs($member)->get($this->url('/app/rutina'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Member/Routine')
                ->where('routine.name', 'Mía')
                ->where('today', 3)
                ->has('days', 7)
                ->has('days.0.items', 1)
                ->where('days.0.items.0.exercise.name', 'Sentadilla')
                ->where('days.0.items.0.reps', '10-12')
                ->has('days.2.items', 0));
    }

    public function test_member_without_routine_gets_empty_state(): void
    {
        $member = $this->user($this->iron);

        $this->actingAs($member)->get($this->url('/app/rutina'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Member/Routine')->where('routine', null));
    }

    public function test_staff_cannot_open_member_routine(): void
    {
        $trainer = $this->user($this->iron, Role::Trainer);

        $this->actingAs($trainer)->get($this->url('/app/rutina'))->assertForbidden();
    }

    public function test_today_card_props_for_training_and_rest_days(): void
    {
        $member = $this->user($this->iron);
        $this->assign($this->routineWithMonday('Mía', 'Sentadilla'), $member);
        $service = app(TodayRoutine::class);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-16 08:00', TodayRoutine::TIMEZONE)); // lunes
        $monday = $this->inGym($this->iron, fn () => $service->forMemberHome($member));
        $this->assertTrue($monday['hasRoutine']);
        $this->assertFalse($monday['isRestDay']);
        $this->assertSame('Lunes', $monday['dayName']);
        $this->assertSame('Sentadilla', $monday['exercises'][0]['name']);
        $this->assertSame('/app/rutina', $monday['href']);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-17 08:00', TodayRoutine::TIMEZONE)); // martes
        $tuesday = $this->inGym($this->iron, fn () => $service->forMemberHome($member));
        $this->assertTrue($tuesday['isRestDay']);
        $this->assertSame([], $tuesday['exercises']);

        $nobody = $this->user($this->iron);
        $none = $this->inGym($this->iron, fn () => $service->forMemberHome($nobody));
        $this->assertFalse($none['hasRoutine']);
        $this->assertFalse($none['isRestDay']);
    }
}
