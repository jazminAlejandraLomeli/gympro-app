<?php

namespace Tests\Feature\Training;

use App\Enums\Role;
use Inertia\Testing\AssertableInertia as Assert;

class RoutineHistoryTabTest extends TrainingTestCase
{
    public function test_history_lists_personal_routines_with_filters(): void
    {
        $this->freeze('2026-11-20 10:00');
        $coach = $this->user($this->iron, Role::Trainer, ['name' => 'Coach Ana']);
        $laura = $this->user($this->iron, Role::Member, ['name' => 'Laura']);
        $pedro = $this->user($this->iron, Role::Member, ['name' => 'Pedro']);
        $byCoach = $this->personal($this->iron, $laura, $coach, 'weekly', ['name' => 'Semana Laura']);
        $this->personal($this->iron, $pedro, $pedro, 'single', ['name' => 'Cardio Pedro']);
        $this->template($this->iron, 'weekly', ['name' => 'Plantilla']);
        $this->personal($this->titan, $this->user($this->titan), null, 'weekly', ['name' => 'Ajena']);
        $this->assign($this->iron, $byCoach, $laura, '2026-11-15');

        $this->actingAs($coach)->get($this->url('/panel/rutinas?tab=historial'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'historial')
                ->has('history.data', 2));

        $this->actingAs($coach)->get($this->url('/panel/rutinas?tab=historial&creador=socio'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('history.data', 1)
                ->where('history.data.0.name', 'Cardio Pedro')
                ->where('history.data.0.author', 'Creada por el socio')
                ->where('history.data.0.is_current', false));

        $this->actingAs($coach)->get($this->url('/panel/rutinas?tab=historial&creador=coach&tipo=weekly&q=laur'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('history.data', 1)
                ->where('history.data.0.member.name', 'Laura')
                ->where('history.data.0.author', 'Coach Ana')
                ->where('history.data.0.is_current', true));

        $this->actingAs($coach)->get($this->url('/panel/rutinas?tab=plantillas'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('templates', 1)
                ->where('templates.0.name', 'Plantilla')
                ->has('members', 2));
    }
}
