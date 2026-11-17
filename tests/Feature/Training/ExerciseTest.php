<?php

namespace Tests\Feature\Training;

use App\Enums\Role;
use App\Models\Exercise;
use Inertia\Testing\AssertableInertia as Assert;

class ExerciseTest extends TrainingTestCase
{
    public function test_trainer_creates_an_exercise_in_his_gym(): void
    {
        $trainer = $this->user($this->iron, Role::Trainer);

        $this->actingAs($trainer)->post($this->url('/panel/ejercicios'), [
            'name' => 'Press de banca',
            'muscle_group' => 'pecho',
            'instructions' => 'Baja controlado.',
            'gym_id' => $this->titan->id, // se ignora
        ])->assertRedirect()->assertSessionHas('success');

        $exercise = Exercise::withoutGlobalScopes()->sole();
        $this->assertSame($this->iron->id, $exercise->gym_id);
        $this->assertSame($trainer->id, $exercise->created_by);
        $this->assertSame(Exercise::CLIP_NONE, $exercise->clip_status);
    }

    public function test_gym_admin_lists_and_filters_by_muscle_group(): void
    {
        $admin = $this->user($this->iron, Role::GymAdmin);
        $this->exercise($this->iron, ['name' => 'Sentadilla', 'muscle_group' => 'piernas']);
        $this->exercise($this->iron, ['name' => 'Press', 'muscle_group' => 'pecho']);
        $this->exercise($this->titan, ['name' => 'Ajeno', 'muscle_group' => 'piernas']);

        $this->actingAs($admin)->get($this->url('/panel/ejercicios?grupo=piernas'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Gym/Exercises/Index')
                ->has('exercises.data', 1)
                ->where('exercises.data.0.name', 'Sentadilla')
                ->where('filters.grupo', 'piernas'));
    }

    public function test_member_cannot_manage_exercises(): void
    {
        $member = $this->user($this->iron);

        $this->actingAs($member)->get($this->url('/panel/ejercicios'))->assertForbidden();
        $this->actingAs($member)->post($this->url('/panel/ejercicios'), ['name' => 'X', 'muscle_group' => 'pecho'])->assertForbidden();
        $this->actingAs($member)->get($this->url('/panel/rutinas'))->assertForbidden();
    }

    public function test_validates_muscle_group(): void
    {
        $trainer = $this->user($this->iron, Role::Trainer);

        $this->actingAs($trainer)->post($this->url('/panel/ejercicios'), ['name' => 'X', 'muscle_group' => 'orejas'])
            ->assertSessionHasErrors('muscle_group');
    }

    public function test_cannot_edit_an_exercise_of_another_gym(): void
    {
        $trainer = $this->user($this->iron, Role::Trainer);
        $foreign = $this->exercise($this->titan);

        $this->actingAs($trainer)->put($this->url("/panel/ejercicios/{$foreign->id}"), [
            'name' => 'Hackeado', 'muscle_group' => 'pecho',
        ])->assertNotFound();

        $this->assertSame('Sentadilla', Exercise::withoutGlobalScopes()->find($foreign->id)->name);
    }
}
