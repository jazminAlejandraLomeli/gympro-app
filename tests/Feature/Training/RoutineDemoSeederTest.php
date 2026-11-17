<?php

namespace Tests\Feature\Training;

use App\Enums\Role;
use App\Models\Exercise;
use App\Models\Routine;
use App\Models\RoutineRequest;
use App\Services\Training\TodayRoutine;
use Database\Seeders\Demo\RoutineDemoSeeder;

class RoutineDemoSeederTest extends TrainingTestCase
{
    public function test_seeds_library_templates_demo_member_routines_and_requests(): void
    {
        $coach = $this->user($this->iron, Role::Trainer, ['email' => 'coach@irondemo.test']);
        $demo = $this->user($this->iron, Role::Member, ['email' => 'socio@demo.test']);
        foreach (range(1, 5) as $i) {
            $this->user($this->iron);
        }

        $this->inGym($this->iron, fn () => app(RoutineDemoSeeder::class)->seedGym($this->iron));

        $exercises = Exercise::withoutGlobalScopes()->where('gym_id', $this->iron->id);
        $this->assertGreaterThanOrEqual(18, $exercises->count());
        $this->assertSame(0, (clone $exercises)->whereNull('objective')->orWhereNull('equipment')->count());

        $routines = Routine::withoutGlobalScopes()->where('gym_id', $this->iron->id);
        $this->assertSame(2, (clone $routines)->where('is_template', true)->where('type', 'weekly')->count());
        $this->assertSame(1, (clone $routines)->where('member_id', $demo->id)->where('created_by', $demo->id)->count());

        $this->assertSame(2, RoutineRequest::withoutGlobalScopes()->where('gym_id', $this->iron->id)->where('status', 'pending')->count());
        $this->assertSame(0, RoutineRequest::withoutGlobalScopes()->where('user_id', $demo->id)->count());

        $page = $this->inGym($this->iron, fn () => app(TodayRoutine::class)->forRoutinePage($demo));
        $this->assertSame('single', $page['today']['source']);
        $this->assertSame($coach->name, $page['today']['routine']['author']['name']);
        $this->assertNotNull($page['weekly']);
    }
}
