<?php

namespace Tests\Feature\Training;

use App\Enums\Role;
use App\Models\RoutineAssignment;

class RoutineAssignmentTest extends TrainingTestCase
{
    public function test_assigns_to_several_members_and_deactivates_their_previous_routine(): void
    {
        $trainer = $this->user($this->iron, Role::Trainer);
        $old = $this->routine($this->iron, ['name' => 'Vieja']);
        $new = $this->routine($this->iron, ['name' => 'Nueva']);
        $ana = $this->user($this->iron);
        $beto = $this->user($this->iron);

        $this->actingAs($trainer)->post($this->url("/panel/rutinas/{$old->id}/asignaciones"), ['user_ids' => [$ana->id]])
            ->assertRedirect()->assertSessionHas('success');

        $this->actingAs($trainer)->post($this->url("/panel/rutinas/{$new->id}/asignaciones"), [
            'user_ids' => [$ana->id, $beto->id],
            'starts_on' => '2026-11-23',
        ])->assertRedirect()->assertSessionHas('success');

        $active = RoutineAssignment::withoutGlobalScopes()->where('active', true)->get();
        $this->assertCount(2, $active);
        $this->assertTrue($active->every(fn ($a) => $a->routine_id === $new->id && $a->gym_id === $this->iron->id));
        $this->assertSame('2026-11-23', $active->first()->starts_on->toDateString());
        $this->assertSame($trainer->id, $active->first()->assigned_by);
        $this->assertSame(1, RoutineAssignment::withoutGlobalScopes()->where('active', false)->where('user_id', $ana->id)->count());
    }

    public function test_cannot_assign_a_member_of_another_gym(): void
    {
        $trainer = $this->user($this->iron, Role::Trainer);
        $routine = $this->routine($this->iron);
        $foreign = $this->user($this->titan);

        $this->actingAs($trainer)->post($this->url("/panel/rutinas/{$routine->id}/asignaciones"), ['user_ids' => [$foreign->id]])
            ->assertSessionHasErrors('user_ids.0');

        $this->assertSame(0, RoutineAssignment::withoutGlobalScopes()->count());
    }

    public function test_cannot_assign_to_staff(): void
    {
        $admin = $this->user($this->iron, Role::GymAdmin);
        $routine = $this->routine($this->iron);
        $coach = $this->user($this->iron, Role::Trainer);

        $this->actingAs($admin)->post($this->url("/panel/rutinas/{$routine->id}/asignaciones"), ['user_ids' => [$coach->id]])
            ->assertSessionHasErrors('user_ids.0');
    }

    public function test_member_cannot_assign(): void
    {
        $member = $this->user($this->iron);
        $routine = $this->routine($this->iron);

        $this->actingAs($member)->post($this->url("/panel/rutinas/{$routine->id}/asignaciones"), ['user_ids' => [$member->id]])
            ->assertForbidden();
    }

    public function test_unassign_only_within_the_same_routine(): void
    {
        $trainer = $this->user($this->iron, Role::Trainer);
        $routine = $this->routine($this->iron);
        $other = $this->routine($this->iron, ['name' => 'Otra']);
        $member = $this->user($this->iron);
        $assignment = $this->inGym($this->iron, fn () => RoutineAssignment::create([
            'routine_id' => $routine->id, 'user_id' => $member->id, 'starts_on' => '2026-11-16', 'active' => true,
        ]));

        $this->actingAs($trainer)->delete($this->url("/panel/rutinas/{$other->id}/asignaciones/{$assignment->id}"))->assertNotFound();
        $this->assertTrue($assignment->fresh()->active);

        $this->actingAs($trainer)->delete($this->url("/panel/rutinas/{$routine->id}/asignaciones/{$assignment->id}"))->assertRedirect();
        $this->assertFalse($assignment->fresh()->active);
    }
}
