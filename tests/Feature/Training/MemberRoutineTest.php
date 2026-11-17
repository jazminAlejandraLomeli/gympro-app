<?php

namespace Tests\Feature\Training;

use App\Enums\Role;
use App\Models\Routine;
use App\Models\RoutineAssignment;
use App\Services\Training\TodayRoutine;
use Inertia\Testing\AssertableInertia as Assert;

class MemberRoutineTest extends TrainingTestCase
{
    public function test_single_day_routine_for_today_takes_priority_over_the_weekly(): void
    {
        $this->freeze('2026-11-16 10:00'); // lunes
        $coach = $this->user($this->iron, Role::Trainer, ['name' => 'Coach Ana']);
        $member = $this->user($this->iron);
        $weekly = $this->personal($this->iron, $member, $coach, 'weekly', ['name' => 'Semana']);
        $this->addItem($this->iron, $weekly, $this->exercise($this->iron, ['name' => 'Sentadilla']), 1);
        $session = $this->personal($this->iron, $member, $coach, 'single', ['name' => 'Sesión especial']);
        $this->addItem($this->iron, $session, $this->exercise($this->iron, ['name' => 'Remo']), null, ['notes' => 'Espalda recta']);
        $this->assign($this->iron, $weekly, $member, '2026-11-10');
        $this->assign($this->iron, $session, $member, '2026-11-16');

        $this->actingAs($member)->get($this->url('/app/rutina'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Member/Routine')
                ->where('today.source', 'single')
                ->where('today.routine.name', 'Sesión especial')
                ->where('today.routine.author.name', 'Coach Ana')
                ->where('today.routine.author.isSelf', false)
                ->has('today.items', 1)
                ->where('today.items.0.exercise.name', 'Remo')
                ->where('today.items.0.notes', 'Espalda recta')
                ->where('weekly.routine.name', 'Semana')
                ->has('weekly.days', 7)
                ->where('weekly.days.0.items.0.exercise.name', 'Sentadilla'));

        $home = $this->inGym($this->iron, fn () => app(TodayRoutine::class)->forMemberHome($member));
        $this->assertSame('Sesión especial', $home['routineName']);
        $this->assertSame('single', $home['source']);
        $this->assertSame('Coach Ana', $home['assignedBy']);
        $this->assertSame('Remo', $home['exercises'][0]['name']);

        // Al día siguiente vuelve a mandar la semanal (martes = descanso)
        $this->freeze('2026-11-17 10:00');
        $tuesday = $this->inGym($this->iron, fn () => app(TodayRoutine::class)->forMemberHome($member));
        $this->assertSame('Semana', $tuesday['routineName']);
        $this->assertSame('weekly', $tuesday['source']);
        $this->assertTrue($tuesday['isRestDay']);
    }

    public function test_home_card_keeps_its_props(): void
    {
        $this->freeze('2026-11-16 08:00'); // lunes
        $member = $this->user($this->iron);
        $routine = $this->personal($this->iron, $member, $member, 'weekly', ['name' => 'Mía']);
        $this->addItem($this->iron, $routine, $this->exercise($this->iron, ['name' => 'Sentadilla']), 1);
        $this->assign($this->iron, $routine, $member, '2026-11-16');
        $service = app(TodayRoutine::class);

        $monday = $this->inGym($this->iron, fn () => $service->forMemberHome($member));
        foreach (['hasRoutine', 'routineName', 'day', 'dayName', 'isRestDay', 'exercises', 'href', 'source', 'assignedBy', 'createdBySelf'] as $key) {
            $this->assertArrayHasKey($key, $monday);
        }
        $this->assertSame(['id', 'name', 'muscle_group_label', 'sets', 'reps', 'weight_kg'], array_keys($monday['exercises'][0]));
        $this->assertTrue($monday['hasRoutine']);
        $this->assertFalse($monday['isRestDay']);
        $this->assertSame(1, $monday['day']);
        $this->assertSame('Lunes', $monday['dayName']);
        $this->assertSame('/app/rutina', $monday['href']);
        $this->assertTrue($monday['createdBySelf']);
        $this->assertNull($monday['assignedBy']);

        $nobody = $this->user($this->iron);
        $none = $this->inGym($this->iron, fn () => $service->forMemberHome($nobody));
        $this->assertFalse($none['hasRoutine']);
        $this->assertFalse($none['isRestDay']);
        $this->assertSame([], $none['exercises']);
        $this->assertNull($none['source']);

        $this->actingAs($member)->get($this->url('/app'))
            ->assertInertia(fn (Assert $page) => $page->where('todayRoutine.routineName', 'Mía')->has('todayRoutine.exercises', 1));
    }

    public function test_member_without_routine_and_staff_cannot_open_the_portal(): void
    {
        $member = $this->user($this->iron);

        $this->actingAs($member)->get($this->url('/app/rutina'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('today', null)->where('weekly', null)->where('pendingRequest', null));

        $trainer = $this->user($this->iron, Role::Trainer);
        $this->actingAs($trainer)->get($this->url('/app/rutina'))->assertForbidden();
        $this->actingAs($trainer)->get($this->url('/app/rutina/nueva'))->assertForbidden();
    }

    public function test_member_creates_his_own_routines_and_they_are_assigned(): void
    {
        $this->freeze('2026-11-16 10:00');
        $coach = $this->user($this->iron, Role::Trainer);
        $member = $this->user($this->iron);
        $exercise = $this->exercise($this->iron);
        $coachWeekly = $this->personal($this->iron, $member, $coach, 'weekly');
        $this->assign($this->iron, $coachWeekly, $member, '2026-11-10');

        $this->actingAs($member)->get($this->url('/app/rutina/nueva?tipo=dia'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Member/RoutineBuilder')->where('initial.type', 'single')->has('catalog', 1));

        $this->actingAs($member)->post($this->url('/app/rutina'), [
            'name' => 'Mi pierna', 'type' => 'single',
            'member_id' => 999, 'created_by' => 999, 'is_template' => true, // se ignoran
            'items' => [['exercise_id' => $exercise->id, 'sets' => 3, 'reps' => '12', 'notes' => 'Lento']],
        ])->assertRedirect('/app/rutina');

        $single = Routine::withoutGlobalScopes()->where('name', 'Mi pierna')->sole();
        $this->assertSame($member->id, $single->member_id);
        $this->assertSame($member->id, $single->created_by);
        $this->assertFalse($single->is_template);
        $assignment = RoutineAssignment::withoutGlobalScopes()->where('routine_id', $single->id)->sole();
        $this->assertSame('2026-11-16', $assignment->scheduled_for);
        // No tocó la semanal del coach
        $this->assertTrue((bool) RoutineAssignment::withoutGlobalScopes()->where('routine_id', $coachWeekly->id)->value('active'));

        $this->actingAs($member)->post($this->url('/app/rutina'), [
            'name' => 'Mi semana', 'type' => 'weekly',
            'items' => [['day_of_week' => 2, 'exercise_id' => $exercise->id, 'sets' => 3, 'reps' => '12']],
        ])->assertRedirect('/app/rutina');

        $this->assertFalse((bool) RoutineAssignment::withoutGlobalScopes()->where('routine_id', $coachWeekly->id)->value('active'));
        $this->actingAs($member)->get($this->url('/app/rutina'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('today.routine.name', 'Mi pierna')
                ->where('today.routine.author.isSelf', true)
                ->where('weekly.routine.name', 'Mi semana'));
    }

    public function test_member_edits_and_deletes_his_own_but_not_the_coachs(): void
    {
        $coach = $this->user($this->iron, Role::Trainer);
        $member = $this->user($this->iron);
        $exercise = $this->exercise($this->iron);
        $own = $this->personal($this->iron, $member, $member, 'weekly', ['name' => 'Mía']);
        $fromCoach = $this->personal($this->iron, $member, $coach, 'weekly', ['name' => 'Del coach']);
        $this->assign($this->iron, $fromCoach, $member, '2026-11-10');

        $this->actingAs($member)->get($this->url("/app/rutina/{$own->id}/editar"))->assertOk();
        $this->actingAs($member)->put($this->url("/app/rutina/{$own->id}"), [
            'name' => 'Mía 2', 'type' => 'single', // el tipo no cambia
            'items' => [['day_of_week' => 4, 'exercise_id' => $exercise->id, 'sets' => 3, 'reps' => '10']],
        ])->assertRedirect("/app/rutina/{$own->id}");
        $this->assertSame('Mía 2', $own->fresh()->name);
        $this->assertSame('weekly', $own->fresh()->type);
        $this->assertSame(4, $own->items()->sole()->day_of_week);

        // La del coach: la ve, pero no la edita ni la borra
        $this->actingAs($member)->get($this->url("/app/rutina/{$fromCoach->id}"))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Member/RoutineShow')->where('routine.can_edit', false));
        $this->actingAs($member)->get($this->url("/app/rutina/{$fromCoach->id}/editar"))->assertForbidden();
        $this->actingAs($member)->put($this->url("/app/rutina/{$fromCoach->id}"), ['name' => 'Hackeada'])->assertForbidden();
        $this->actingAs($member)->delete($this->url("/app/rutina/{$fromCoach->id}"))->assertForbidden();
        $this->assertSame('Del coach', $fromCoach->fresh()->name);

        $this->actingAs($member)->delete($this->url("/app/rutina/{$own->id}"))->assertRedirect('/app/rutina/historial');
        $this->assertNull(Routine::withoutGlobalScopes()->find($own->id));
    }

    public function test_member_cannot_see_templates_or_other_members_routines(): void
    {
        $coach = $this->user($this->iron, Role::Trainer);
        $member = $this->user($this->iron);
        $other = $this->user($this->iron);
        $template = $this->template($this->iron);
        $theirs = $this->personal($this->iron, $other, $other);
        $foreign = $this->personal($this->titan, $this->user($this->titan), null);

        foreach ([$template, $theirs] as $routine) {
            $this->actingAs($member)->get($this->url("/app/rutina/{$routine->id}"))->assertForbidden();
            $this->actingAs($member)->post($this->url("/app/rutina/{$routine->id}/usar"))->assertForbidden();
            $this->actingAs($member)->put($this->url("/app/rutina/{$routine->id}"), ['name' => 'X'])->assertForbidden();
        }
        $this->actingAs($member)->get($this->url("/app/rutina/{$foreign->id}"))->assertNotFound();

        $this->assertSame(0, RoutineAssignment::withoutGlobalScopes()->count());
    }

    public function test_member_cannot_reference_exercises_of_another_gym(): void
    {
        $member = $this->user($this->iron);
        $foreign = $this->exercise($this->titan);

        $this->actingAs($member)->post($this->url('/app/rutina'), [
            'name' => 'X', 'type' => 'single',
            'items' => [['exercise_id' => $foreign->id, 'sets' => 3, 'reps' => '10']],
        ])->assertSessionHasErrors('items.0.exercise_id');

        $this->assertSame(0, Routine::withoutGlobalScopes()->count());
    }

    public function test_history_and_reuse(): void
    {
        $this->freeze('2026-11-20 10:00');
        $coach = $this->user($this->iron, Role::Trainer, ['name' => 'Coach Ana']);
        $member = $this->user($this->iron);
        $oldWeekly = $this->personal($this->iron, $member, $coach, 'weekly', ['name' => 'Vieja']);
        $newWeekly = $this->personal($this->iron, $member, $coach, 'weekly', ['name' => 'Actual']);
        $pastSession = $this->personal($this->iron, $member, $member, 'single', ['name' => 'Cardio']);
        $this->assign($this->iron, $oldWeekly, $member, '2026-11-01');
        $this->assign($this->iron, $pastSession, $member, '2026-11-12');
        $this->assign($this->iron, $newWeekly, $member, '2026-11-15');

        $this->actingAs($member)->get($this->url('/app/rutina/historial'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Member/RoutineHistory')
                ->has('assignments.data', 3)
                ->where('assignments.data.0.routine.name', 'Actual')
                ->where('assignments.data.0.is_current', true)
                ->where('assignments.data.0.routine.author.name', 'Coach Ana')
                ->where('assignments.data.1.routine.name', 'Cardio')
                ->where('assignments.data.1.date', '2026-11-12')
                ->where('assignments.data.1.is_current', false)
                ->where('assignments.data.1.routine.can_edit', true)
                ->where('assignments.data.2.routine.can_edit', false));

        // Semanal: se reactiva y desactiva la actual
        $this->actingAs($member)->post($this->url("/app/rutina/{$oldWeekly->id}/usar"))->assertRedirect('/app/rutina');
        $weekly = app(TodayRoutine::class);
        $this->assertSame($oldWeekly->id, $this->inGym($this->iron, fn () => $weekly->activeWeekly($member))->routine_id);

        // De un día: se programa para hoy
        $this->actingAs($member)->post($this->url("/app/rutina/{$pastSession->id}/usar"))->assertRedirect('/app/rutina');
        $this->assertSame(1, RoutineAssignment::withoutGlobalScopes()
            ->where('routine_id', $pastSession->id)->where('scheduled_for', '2026-11-20')->where('active', true)->count());
        $this->actingAs($member)->get($this->url('/app/rutina'))
            ->assertInertia(fn (Assert $page) => $page->where('today.routine.name', 'Cardio')->where('weekly.routine.name', 'Vieja'));
    }
}
