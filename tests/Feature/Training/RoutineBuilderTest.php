<?php

namespace Tests\Feature\Training;

use App\Enums\Role;
use App\Models\Routine;
use App\Models\RoutineItem;
use Inertia\Testing\AssertableInertia as Assert;

class RoutineBuilderTest extends TrainingTestCase
{
    public function test_trainer_creates_a_routine_and_is_sent_to_the_builder(): void
    {
        $trainer = $this->user($this->iron, Role::Trainer);

        $response = $this->actingAs($trainer)->post($this->url('/panel/rutinas'), ['name' => 'Fuerza', 'goal' => 'Ganar fuerza']);

        $routine = Routine::withoutGlobalScopes()->sole();
        $response->assertRedirect("/panel/rutinas/{$routine->id}");
        $this->assertSame($this->iron->id, $routine->gym_id);
        $this->assertSame($trainer->id, $routine->created_by);
    }

    public function test_saves_the_whole_week_in_one_request_with_positions(): void
    {
        $admin = $this->user($this->iron, Role::GymAdmin);
        $routine = $this->routine($this->iron);
        $squat = $this->exercise($this->iron, ['name' => 'Sentadilla']);
        $bench = $this->exercise($this->iron, ['name' => 'Press', 'muscle_group' => 'pecho']);
        // Renglón previo que debe desaparecer
        $this->inGym($this->iron, fn () => $routine->items()->create(['day_of_week' => 2, 'exercise_id' => $squat->id, 'position' => 0, 'sets' => 1, 'reps' => '1']));

        $this->actingAs($admin)->put($this->url("/panel/rutinas/{$routine->id}"), [
            'name' => 'Fuerza 2',
            'goal' => null,
            'notes' => 'Calienta',
            'items' => [
                ['day_of_week' => 1, 'exercise_id' => $bench->id, 'sets' => 4, 'reps' => '8-10', 'weight_kg' => 40.5, 'rest_seconds' => 90],
                ['day_of_week' => 1, 'exercise_id' => $squat->id, 'sets' => 3, 'reps' => '10-12', 'weight_kg' => null, 'rest_seconds' => 60],
                ['day_of_week' => 5, 'exercise_id' => $squat->id, 'sets' => 5, 'reps' => 'al fallo', 'weight_kg' => 60, 'rest_seconds' => null],
            ],
        ])->assertRedirect()->assertSessionHas('success');

        $items = RoutineItem::withoutGlobalScopes()->orderBy('day_of_week')->orderBy('position')->get();
        $this->assertCount(3, $items);
        $this->assertSame([[1, 0, $bench->id], [1, 1, $squat->id], [5, 0, $squat->id]],
            $items->map(fn ($i) => [$i->day_of_week, $i->position, $i->exercise_id])->all());
        $this->assertSame('8-10', $items[0]->reps);
        $this->assertSame('40.50', $items[0]->weight_kg);
        $this->assertTrue($items->every(fn ($i) => $i->gym_id === $this->iron->id));
        $this->assertSame('Fuerza 2', $routine->fresh()->name);
    }

    public function test_rejects_an_exercise_from_another_gym(): void
    {
        $trainer = $this->user($this->iron, Role::Trainer);
        $routine = $this->routine($this->iron);
        $foreign = $this->exercise($this->titan);

        $this->actingAs($trainer)->put($this->url("/panel/rutinas/{$routine->id}"), [
            'name' => 'Fuerza',
            'items' => [['day_of_week' => 1, 'exercise_id' => $foreign->id, 'sets' => 3, 'reps' => '10']],
        ])->assertSessionHasErrors('items.0.exercise_id');

        $this->assertSame(0, RoutineItem::withoutGlobalScopes()->count());
    }

    public function test_validates_day_sets_and_reps(): void
    {
        $trainer = $this->user($this->iron, Role::Trainer);
        $routine = $this->routine($this->iron);
        $exercise = $this->exercise($this->iron);

        $this->actingAs($trainer)->put($this->url("/panel/rutinas/{$routine->id}"), [
            'name' => 'Fuerza',
            'items' => [['day_of_week' => 8, 'exercise_id' => $exercise->id, 'sets' => 0, 'reps' => '']],
        ])->assertSessionHasErrors(['items.0.day_of_week', 'items.0.sets', 'items.0.reps']);
    }

    public function test_builder_page_shows_only_own_gym_catalog(): void
    {
        $trainer = $this->user($this->iron, Role::Trainer);
        $routine = $this->routine($this->iron);
        $this->exercise($this->iron, ['name' => 'Propio']);
        $this->exercise($this->titan, ['name' => 'Ajeno']);
        $this->user($this->iron, Role::Member, ['name' => 'Socio Iron']);
        $this->user($this->titan, Role::Member, ['name' => 'Socio Titan']);

        $this->actingAs($trainer)->get($this->url("/panel/rutinas/{$routine->id}"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Gym/Routines/Edit')
                ->has('catalog', 1)
                ->where('catalog.0.name', 'Propio')
                ->has('members', 1)
                ->where('members.0.name', 'Socio Iron'));
    }

    public function test_cannot_open_a_routine_of_another_gym(): void
    {
        $trainer = $this->user($this->iron, Role::Trainer);
        $foreign = $this->routine($this->titan);

        $this->actingAs($trainer)->get($this->url("/panel/rutinas/{$foreign->id}"))->assertNotFound();
        $this->actingAs($trainer)->put($this->url("/panel/rutinas/{$foreign->id}"), ['name' => 'X'])->assertNotFound();
    }

    public function test_member_cannot_build_routines(): void
    {
        $member = $this->user($this->iron);
        $routine = $this->routine($this->iron);

        $this->actingAs($member)->post($this->url('/panel/rutinas'), ['name' => 'X'])->assertForbidden();
        $this->actingAs($member)->put($this->url("/panel/rutinas/{$routine->id}"), ['name' => 'X'])->assertForbidden();
    }
}
