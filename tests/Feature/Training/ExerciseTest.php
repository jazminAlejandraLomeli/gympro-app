<?php

namespace Tests\Feature\Training;

use App\Enums\Role;
use App\Models\Exercise;
use Inertia\Testing\AssertableInertia as Assert;

class ExerciseTest extends TrainingTestCase
{
    public function test_trainer_creates_an_exercise_with_library_fields_and_lands_on_its_video_step(): void
    {
        $trainer = $this->user($this->iron, Role::Trainer);

        $response = $this->actingAs($trainer)->post($this->url('/panel/ejercicios'), [
            'name' => 'Press de banca',
            'muscle_group' => 'pecho',
            'objective' => 'Fuerza de pecho y tríceps',
            'equipment' => 'barra',
            'difficulty' => 'intermedio',
            'instructions' => 'Baja controlado.',
            'notes' => 'Usa topes de seguridad.',
            'gym_id' => $this->titan->id, // se ignora
            'created_by' => 999, // se ignora
        ]);

        $exercise = Exercise::withoutGlobalScopes()->sole();
        $response->assertRedirect("/panel/ejercicios?editar={$exercise->id}")->assertSessionHas('success');
        $this->assertSame($this->iron->id, $exercise->gym_id);
        $this->assertSame($trainer->id, $exercise->created_by);
        $this->assertSame(Exercise::CLIP_NONE, $exercise->clip_status);
        $this->assertSame('barra', $exercise->equipment);
        $this->assertSame('intermedio', $exercise->difficulty);
        $this->assertSame('Fuerza de pecho y tríceps', $exercise->objective);
        $this->assertSame('Usa topes de seguridad.', $exercise->notes);

        $this->actingAs($trainer)->get($this->url("/panel/ejercicios?editar={$exercise->id}"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Gym/Exercises/Index')
                ->where('editing.id', $exercise->id)
                ->where('editing.equipment_label', 'Barra')
                ->has('equipment', count(Exercise::EQUIPMENT))
                ->has('difficulties', 3));
    }

    public function test_gym_admin_filters_by_muscle_group_equipment_and_search(): void
    {
        $admin = $this->user($this->iron, Role::GymAdmin);
        $this->exercise($this->iron, ['name' => 'Sentadilla', 'muscle_group' => 'piernas', 'equipment' => 'barra']);
        $this->exercise($this->iron, ['name' => 'Prensa', 'muscle_group' => 'piernas', 'equipment' => 'maquina']);
        $this->exercise($this->iron, ['name' => 'Press', 'muscle_group' => 'pecho', 'equipment' => 'barra']);
        $this->exercise($this->titan, ['name' => 'Ajeno', 'muscle_group' => 'piernas', 'equipment' => 'barra']);

        $this->actingAs($admin)->get($this->url('/panel/ejercicios?grupo=piernas'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Gym/Exercises/Index')
                ->has('exercises.data', 2)
                ->where('filters.grupo', 'piernas'));

        $this->actingAs($admin)->get($this->url('/panel/ejercicios?grupo=piernas&equipo=barra'))
            ->assertInertia(fn (Assert $page) => $page->has('exercises.data', 1)->where('exercises.data.0.name', 'Sentadilla'));

        $this->actingAs($admin)->get($this->url('/panel/ejercicios?q=pren'))
            ->assertInertia(fn (Assert $page) => $page->has('exercises.data', 1)->where('exercises.data.0.name', 'Prensa'));
    }

    public function test_member_cannot_manage_exercises_or_enter_the_panel(): void
    {
        $member = $this->user($this->iron);

        $this->actingAs($member)->get($this->url('/panel/ejercicios'))->assertForbidden();
        $this->actingAs($member)->post($this->url('/panel/ejercicios'), ['name' => 'X', 'muscle_group' => 'pecho'])->assertForbidden();
        $this->actingAs($member)->get($this->url('/panel/rutinas'))->assertForbidden();
        $this->actingAs($member)->get($this->url('/panel/rutinas/nueva'))->assertForbidden();
    }

    public function test_validates_lists(): void
    {
        $trainer = $this->user($this->iron, Role::Trainer);

        $this->actingAs($trainer)->post($this->url('/panel/ejercicios'), [
            'name' => 'X', 'muscle_group' => 'orejas', 'equipment' => 'cohete', 'difficulty' => 'imposible', 'objective' => str_repeat('a', 151),
        ])->assertSessionHasErrors(['muscle_group', 'equipment', 'difficulty', 'objective']);
    }

    public function test_cannot_edit_an_exercise_of_another_gym(): void
    {
        $trainer = $this->user($this->iron, Role::Trainer);
        $foreign = $this->exercise($this->titan);

        $this->actingAs($trainer)->put($this->url("/panel/ejercicios/{$foreign->id}"), [
            'name' => 'Hackeado', 'muscle_group' => 'pecho',
        ])->assertNotFound();

        $this->actingAs($trainer)->get($this->url("/panel/ejercicios?editar={$foreign->id}"))
            ->assertInertia(fn (Assert $page) => $page->where('editing', null));

        $this->assertSame('Sentadilla', Exercise::withoutGlobalScopes()->find($foreign->id)->name);
    }
}
