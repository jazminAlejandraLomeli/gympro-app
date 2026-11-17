<?php

namespace Tests\Feature\Training;

use App\Enums\Role;
use App\Models\Routine;
use App\Models\RoutineAssignment;

class RoutineAssignmentTest extends TrainingTestCase
{
    public function test_assigning_a_template_duplicates_it_per_member(): void
    {
        $this->freeze('2026-11-16 09:00');
        $trainer = $this->user($this->iron, Role::Trainer);
        $template = $this->template($this->iron, 'weekly', ['name' => 'Nueva']);
        $this->addItem($this->iron, $template, $this->exercise($this->iron));
        $ana = $this->user($this->iron);
        $beto = $this->user($this->iron);
        $old = $this->personal($this->iron, $ana, $trainer, 'weekly', ['name' => 'Vieja']);
        $this->assign($this->iron, $old, $ana, '2026-11-01');

        $this->actingAs($trainer)->post($this->url("/panel/rutinas/{$template->id}/asignaciones"), [
            'user_ids' => [$ana->id, $beto->id],
            'date' => '2026-11-23',
        ])->assertRedirect()->assertSessionHas('success');

        $active = RoutineAssignment::withoutGlobalScopes()->where('active', true)->with('routine')->get();
        $this->assertCount(2, $active);
        foreach ($active as $assignment) {
            $this->assertNotSame($template->id, $assignment->routine_id);
            $this->assertSame('Nueva', $assignment->routine->name);
            $this->assertFalse($assignment->routine->is_template);
            $this->assertSame($assignment->user_id, $assignment->routine->member_id);
            $this->assertSame(1, $assignment->routine->items()->count());
            $this->assertSame('2026-11-23', $assignment->starts_on->toDateString());
            $this->assertSame($trainer->id, $assignment->assigned_by);
        }
        $this->assertSame(1, RoutineAssignment::withoutGlobalScopes()->where('active', false)->where('user_id', $ana->id)->count());
    }

    public function test_weekly_and_single_day_assignments_do_not_replace_each_other(): void
    {
        $this->freeze('2026-11-16 09:00');
        $trainer = $this->user($this->iron, Role::Trainer);
        $member = $this->user($this->iron);
        $weekly = $this->personal($this->iron, $member, $trainer, 'weekly');
        $single = $this->personal($this->iron, $member, $trainer, 'single');
        $otherSingle = $this->personal($this->iron, $member, $trainer, 'single');
        $newWeekly = $this->personal($this->iron, $member, $trainer, 'weekly');

        $this->assign($this->iron, $weekly, $member, '2026-11-16');
        $this->assign($this->iron, $single, $member, '2026-11-16');
        $isActive = fn (Routine $r) => (bool) RoutineAssignment::withoutGlobalScopes()->where('routine_id', $r->id)->value('active');

        // La de un día no tocó la semanal
        $this->assertTrue($isActive($weekly));
        $this->assertTrue($isActive($single));

        // La nueva semanal no tocó la de un día
        $this->assign($this->iron, $newWeekly, $member, '2026-11-16');
        $this->assertFalse($isActive($weekly));
        $this->assertTrue($isActive($newWeekly));
        $this->assertTrue($isActive($single));

        // Otra de un día para la misma fecha la reemplaza; en otra fecha convive
        $this->assign($this->iron, $otherSingle, $member, '2026-11-16');
        $this->assertFalse($isActive($single));
        $this->assertTrue($isActive($otherSingle));
        $this->assign($this->iron, $single, $member, '2026-11-18');
        $this->assertTrue($isActive($otherSingle));
        $this->assertTrue($isActive($newWeekly));
    }

    public function test_assigning_a_single_day_template_uses_the_date(): void
    {
        $admin = $this->user($this->iron, Role::GymAdmin);
        $template = $this->template($this->iron, 'single');
        $member = $this->user($this->iron);

        $this->actingAs($admin)->post($this->url("/panel/rutinas/{$template->id}/asignaciones"), [
            'user_ids' => [$member->id], 'date' => '2026-11-20',
        ])->assertRedirect();

        $this->assertSame('2026-11-20', RoutineAssignment::withoutGlobalScopes()->sole()->scheduled_for);
    }

    public function test_cannot_assign_members_of_another_gym_or_staff(): void
    {
        $trainer = $this->user($this->iron, Role::Trainer);
        $template = $this->template($this->iron);
        $foreign = $this->user($this->titan);
        $coach = $this->user($this->iron, Role::Trainer);

        $this->actingAs($trainer)->post($this->url("/panel/rutinas/{$template->id}/asignaciones"), ['user_ids' => [$foreign->id]])
            ->assertSessionHasErrors('user_ids.0');
        $this->actingAs($trainer)->post($this->url("/panel/rutinas/{$template->id}/asignaciones"), ['user_ids' => [$coach->id]])
            ->assertSessionHasErrors('user_ids.0');

        $this->assertSame(0, RoutineAssignment::withoutGlobalScopes()->count());
        $this->assertSame(1, Routine::withoutGlobalScopes()->count());
    }

    public function test_only_templates_can_be_assigned_and_members_cannot_assign(): void
    {
        $trainer = $this->user($this->iron, Role::Trainer);
        $member = $this->user($this->iron);
        $personal = $this->personal($this->iron, $member, $trainer);
        $template = $this->template($this->iron);

        $this->actingAs($trainer)->post($this->url("/panel/rutinas/{$personal->id}/asignaciones"), ['user_ids' => [$member->id]])
            ->assertForbidden();
        $this->actingAs($member)->post($this->url("/panel/rutinas/{$template->id}/asignaciones"), ['user_ids' => [$member->id]])
            ->assertForbidden();
    }

    public function test_unassign_only_within_the_same_routine(): void
    {
        $trainer = $this->user($this->iron, Role::Trainer);
        $member = $this->user($this->iron);
        $routine = $this->personal($this->iron, $member, $trainer);
        $other = $this->personal($this->iron, $member, $trainer, 'single');
        $this->assign($this->iron, $routine, $member, '2026-11-16');
        $assignment = RoutineAssignment::withoutGlobalScopes()->sole();

        $this->actingAs($trainer)->delete($this->url("/panel/rutinas/{$other->id}/asignaciones/{$assignment->id}"))->assertNotFound();
        $this->assertTrue($assignment->fresh()->active);

        $this->actingAs($trainer)->delete($this->url("/panel/rutinas/{$routine->id}/asignaciones/{$assignment->id}"))->assertRedirect();
        $this->assertFalse($assignment->fresh()->active);
    }
}
