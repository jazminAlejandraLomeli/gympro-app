<?php

namespace Tests\Feature\Training;

use App\Enums\Role;
use App\Models\Exercise;
use App\Models\Routine;
use App\Models\RoutineAssignment;
use Database\Seeders\Demo\RoutineDemoSeeder;

class RoutineDemoSeederTest extends TrainingTestCase
{
    public function test_seeds_catalog_routines_and_assigns_the_demo_member(): void
    {
        $this->user($this->iron, Role::Trainer, ['email' => 'coach@irondemo.test']);
        $demo = $this->user($this->iron, Role::Member, ['email' => 'socio@demo.test']);
        $this->user($this->iron);

        $this->inGym($this->iron, fn () => app(RoutineDemoSeeder::class)->seedGym($this->iron));

        $this->assertSame(15, Exercise::withoutGlobalScopes()->where('gym_id', $this->iron->id)->where('clip_status', 'none')->count());
        $this->assertSame(2, Routine::withoutGlobalScopes()->where('gym_id', $this->iron->id)->count());
        $this->assertSame(1, RoutineAssignment::withoutGlobalScopes()->where('user_id', $demo->id)->where('active', true)->count());
    }
}
