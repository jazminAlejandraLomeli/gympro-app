<?php

namespace Tests\Feature\Training;

use App\Enums\Role;
use App\Models\Routine;
use App\Models\RoutineAssignment;
use App\Models\RoutineItem;
use Inertia\Testing\AssertableInertia as Assert;

class RoutineBuilderTest extends TrainingTestCase
{
    public function test_trainer_creates_a_weekly_template_in_one_request(): void
    {
        $this->freeze('2026-11-16 09:00');
        $trainer = $this->user($this->iron, Role::Trainer);
        $squat = $this->exercise($this->iron);

        $this->actingAs($trainer)->post($this->url('/panel/rutinas'), [
            'name' => 'Fuerza',
            'type' => 'weekly',
            'goal' => 'Ganar fuerza',
            'target' => 'template',
            'member_id' => 999, // se ignora en plantillas
            'is_template' => false, // no es fillable
            'items' => [
                ['day_of_week' => 1, 'exercise_id' => $squat->id, 'sets' => 4, 'reps' => '8', 'notes' => 'Baja lento'],
                ['day_of_week' => 3, 'exercise_id' => $squat->id, 'sets' => 3, 'reps' => '10'],
            ],
        ])->assertRedirect('/panel/rutinas?tab=plantillas')->assertSessionHas('success');

        $routine = Routine::withoutGlobalScopes()->sole();
        $this->assertTrue($routine->is_template);
        $this->assertNull($routine->member_id);
        $this->assertSame($trainer->id, $routine->created_by);
        $this->assertSame($this->iron->id, $routine->gym_id);
        $this->assertSame(2, RoutineItem::withoutGlobalScopes()->count());
        $this->assertSame('Baja lento', RoutineItem::withoutGlobalScopes()->where('day_of_week', 1)->value('notes'));
        $this->assertSame(0, RoutineAssignment::withoutGlobalScopes()->count());
    }

    public function test_creating_a_single_day_routine_for_a_member_assigns_it_on_that_date(): void
    {
        $this->freeze('2026-11-16 09:00');
        $admin = $this->user($this->iron, Role::GymAdmin);
        $member = $this->user($this->iron);
        $squat = $this->exercise($this->iron);
        $bench = $this->exercise($this->iron, ['name' => 'Press', 'muscle_group' => 'pecho']);

        $this->actingAs($admin)->post($this->url('/panel/rutinas'), [
            'name' => 'Sesión de hoy',
            'type' => 'single',
            'target' => 'member',
            'member_id' => $member->id,
            'scheduled_for' => '2026-11-17',
            'items' => [
                ['day_of_week' => 5, 'exercise_id' => $squat->id, 'sets' => 4, 'reps' => '8'], // el día se ignora
                ['exercise_id' => $bench->id, 'sets' => 3, 'reps' => '10'],
            ],
        ])->assertRedirect('/panel/rutinas?tab=historial');

        $routine = Routine::withoutGlobalScopes()->sole();
        $this->assertFalse($routine->is_template);
        $this->assertSame($member->id, $routine->member_id);
        $this->assertSame($admin->id, $routine->created_by);
        $this->assertSame('single', $routine->type);
        $this->assertSame([[null, 0], [null, 1]], RoutineItem::withoutGlobalScopes()->orderBy('position')->get()
            ->map(fn ($i) => [$i->day_of_week, $i->position])->all());

        $assignment = RoutineAssignment::withoutGlobalScopes()->sole();
        $this->assertSame($member->id, $assignment->user_id);
        $this->assertSame('2026-11-17', $assignment->scheduled_for);
        $this->assertSame($admin->id, $assignment->assigned_by);
        $this->assertTrue($assignment->active);
    }

    public function test_create_page_is_prefilled_from_the_query(): void
    {
        $trainer = $this->user($this->iron, Role::Trainer);
        $member = $this->user($this->iron, Role::Member, ['name' => 'Laura Pérez']);
        $this->exercise($this->iron, ['name' => 'Propio']);
        $this->exercise($this->titan, ['name' => 'Ajeno']);
        $this->user($this->titan, Role::Member, ['name' => 'Socio Titan']);

        $this->actingAs($trainer)->get($this->url("/panel/rutinas/nueva?tipo=dia&socio={$member->id}"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Gym/Routines/Builder')
                ->where('mode', 'create')
                ->where('initial.type', 'single')
                ->where('initial.target', 'member')
                ->where('initial.member_id', $member->id)
                ->where('initial.name', 'Sesión de Laura')
                ->has('catalog', 1)
                ->where('catalog.0.name', 'Propio')
                ->has('members', 1)
                ->where('members.0.name', 'Laura Pérez'));

        $this->actingAs($trainer)->get($this->url('/panel/rutinas/nueva?tipo=semana'))
            ->assertInertia(fn (Assert $page) => $page->where('initial.type', 'weekly')->where('initial.target', 'template'));
    }

    public function test_saves_the_whole_week_with_positions_and_keeps_the_type(): void
    {
        $admin = $this->user($this->iron, Role::GymAdmin);
        $routine = $this->template($this->iron);
        $squat = $this->exercise($this->iron, ['name' => 'Sentadilla']);
        $bench = $this->exercise($this->iron, ['name' => 'Press', 'muscle_group' => 'pecho']);
        $this->addItem($this->iron, $routine, $squat, 2); // renglón previo que debe desaparecer

        $this->actingAs($admin)->put($this->url("/panel/rutinas/{$routine->id}"), [
            'name' => 'Fuerza 2',
            'type' => 'single', // no se puede cambiar al editar
            'notes' => 'Calienta',
            'items' => [
                ['day_of_week' => 1, 'exercise_id' => $bench->id, 'sets' => 4, 'reps' => '8-10', 'weight_kg' => 40.5, 'rest_seconds' => 90],
                ['day_of_week' => 1, 'exercise_id' => $squat->id, 'sets' => 3, 'reps' => '10-12', 'weight_kg' => null, 'rest_seconds' => 60],
                ['day_of_week' => 5, 'exercise_id' => $squat->id, 'sets' => 5, 'reps' => 'al fallo', 'weight_kg' => 60, 'rest_seconds' => null],
            ],
        ])->assertRedirect('/panel/rutinas?tab=plantillas')->assertSessionHas('success');

        $items = RoutineItem::withoutGlobalScopes()->orderBy('day_of_week')->orderBy('position')->get();
        $this->assertSame([[1, 0, $bench->id], [1, 1, $squat->id], [5, 0, $squat->id]],
            $items->map(fn ($i) => [$i->day_of_week, $i->position, $i->exercise_id])->all());
        $this->assertSame('40.50', $items[0]->weight_kg);
        $this->assertTrue($items->every(fn ($i) => $i->gym_id === $this->iron->id));
        $this->assertSame('Fuerza 2', $routine->fresh()->name);
        $this->assertSame('weekly', $routine->fresh()->type);
    }

    public function test_rejects_exercises_and_members_from_another_gym_or_staff(): void
    {
        $trainer = $this->user($this->iron, Role::Trainer);
        $foreignExercise = $this->exercise($this->titan);
        $foreignMember = $this->user($this->titan);
        $coach = $this->user($this->iron, Role::Trainer);
        $own = $this->exercise($this->iron);

        $this->actingAs($trainer)->post($this->url('/panel/rutinas'), [
            'name' => 'Fuerza', 'type' => 'weekly', 'target' => 'template',
            'items' => [['day_of_week' => 1, 'exercise_id' => $foreignExercise->id, 'sets' => 3, 'reps' => '10']],
        ])->assertSessionHasErrors('items.0.exercise_id');

        foreach ([$foreignMember, $coach] as $notAMember) {
            $this->actingAs($trainer)->post($this->url('/panel/rutinas'), [
                'name' => 'Fuerza', 'type' => 'single', 'target' => 'member', 'member_id' => $notAMember->id,
                'items' => [['exercise_id' => $own->id, 'sets' => 3, 'reps' => '10']],
            ])->assertSessionHasErrors('member_id');
        }

        $this->assertSame(0, Routine::withoutGlobalScopes()->count());
    }

    public function test_validates_day_sets_reps_and_note(): void
    {
        $trainer = $this->user($this->iron, Role::Trainer);
        $routine = $this->template($this->iron);
        $exercise = $this->exercise($this->iron);

        $this->actingAs($trainer)->put($this->url("/panel/rutinas/{$routine->id}"), [
            'name' => 'Fuerza',
            'items' => [
                ['day_of_week' => 8, 'exercise_id' => $exercise->id, 'sets' => 0, 'reps' => ''],
                ['exercise_id' => $exercise->id, 'sets' => 3, 'reps' => '10', 'notes' => str_repeat('x', 201)],
            ],
        ])->assertSessionHasErrors(['items.0.day_of_week', 'items.0.sets', 'items.0.reps', 'items.1.day_of_week', 'items.1.notes']);
    }

    public function test_edit_page_and_duplicate(): void
    {
        $trainer = $this->user($this->iron, Role::Trainer);
        $routine = $this->template($this->iron, 'weekly', ['name' => 'Base']);
        $this->addItem($this->iron, $routine, $this->exercise($this->iron), 3);

        $this->actingAs($trainer)->get($this->url("/panel/rutinas/{$routine->id}/editar"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Gym/Routines/Builder')
                ->where('mode', 'edit')
                ->where('routine.is_template', true)
                ->has('initial.items', 1)
                ->where('initial.items.0.day_of_week', 3));

        $response = $this->actingAs($trainer)->post($this->url("/panel/rutinas/{$routine->id}/duplicar"));

        $copy = Routine::withoutGlobalScopes()->where('name', 'Base (copia)')->sole();
        $response->assertRedirect("/panel/rutinas/{$copy->id}/editar");
        $this->assertTrue($copy->is_template);
        $this->assertSame($trainer->id, $copy->created_by);
        $this->assertSame(1, $copy->items()->count());
    }

    public function test_cannot_open_a_routine_of_another_gym(): void
    {
        $trainer = $this->user($this->iron, Role::Trainer);
        $foreign = $this->template($this->titan);

        $this->actingAs($trainer)->get($this->url("/panel/rutinas/{$foreign->id}/editar"))->assertNotFound();
        $this->actingAs($trainer)->put($this->url("/panel/rutinas/{$foreign->id}"), ['name' => 'X'])->assertNotFound();
        $this->actingAs($trainer)->delete($this->url("/panel/rutinas/{$foreign->id}"))->assertNotFound();
    }

    public function test_trainer_and_admin_can_enter_but_member_cannot_build(): void
    {
        $routine = $this->template($this->iron);

        foreach ([Role::Trainer, Role::GymAdmin] as $role) {
            $staff = $this->user($this->iron, $role);
            $this->actingAs($staff)->get($this->url('/panel/rutinas'))->assertOk();
            $this->actingAs($staff)->get($this->url('/panel/rutinas/nueva'))->assertOk();
            $this->actingAs($staff)->get($this->url("/panel/rutinas/{$routine->id}/editar"))->assertOk();
        }

        $member = $this->user($this->iron);
        $this->actingAs($member)->post($this->url('/panel/rutinas'), ['name' => 'X', 'type' => 'weekly'])->assertForbidden();
        $this->actingAs($member)->put($this->url("/panel/rutinas/{$routine->id}"), ['name' => 'X'])->assertForbidden();
        $this->actingAs($member)->delete($this->url("/panel/rutinas/{$routine->id}"))->assertForbidden();
    }
}
