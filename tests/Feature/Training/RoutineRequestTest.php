<?php

namespace Tests\Feature\Training;

use App\Enums\Role;
use App\Models\Routine;
use App\Models\RoutineAssignment;
use App\Models\RoutineRequest;
use Inertia\Testing\AssertableInertia as Assert;

class RoutineRequestTest extends TrainingTestCase
{
    public function test_member_asks_for_a_routine_only_once_while_pending(): void
    {
        $member = $this->user($this->iron);

        $this->actingAs($member)->post($this->url('/app/rutina/solicitudes'), [
            'kind' => 'weekly', 'goal' => 'Bajar de peso', 'days_per_week' => 4,
            'user_id' => 999, 'status' => 'done', // se ignoran
        ])->assertRedirect()->assertSessionHas('success');

        $request = RoutineRequest::withoutGlobalScopes()->sole();
        $this->assertSame($member->id, $request->user_id);
        $this->assertSame($this->iron->id, $request->gym_id);
        $this->assertSame(RoutineRequest::PENDING, $request->status);
        $this->assertSame(4, $request->days_per_week);

        $this->actingAs($member)->post($this->url('/app/rutina/solicitudes'), ['kind' => 'single', 'goal' => 'Pierna hoy'])
            ->assertSessionHasErrors('request');
        $this->assertSame(1, RoutineRequest::withoutGlobalScopes()->count());

        // Al cancelarla puede pedir otra
        $this->actingAs($member)->delete($this->url("/app/rutina/solicitudes/{$request->id}"))->assertRedirect();
        $this->assertSame(RoutineRequest::CANCELLED, $request->fresh()->status);
        $this->actingAs($member)->post($this->url('/app/rutina/solicitudes'), ['kind' => 'single', 'goal' => 'Pierna hoy', 'days_per_week' => 3])
            ->assertSessionHasNoErrors();
        $this->assertNull(RoutineRequest::withoutGlobalScopes()->latest('id')->first()->days_per_week);
    }

    public function test_member_cannot_cancel_someone_elses_request(): void
    {
        $member = $this->user($this->iron);
        $other = $this->pendingRequest($this->iron, $this->user($this->iron));
        $foreign = $this->pendingRequest($this->titan, $this->user($this->titan));

        $this->actingAs($member)->delete($this->url("/app/rutina/solicitudes/{$other->id}"))->assertNotFound();
        $this->actingAs($member)->delete($this->url("/app/rutina/solicitudes/{$foreign->id}"))->assertNotFound();
        $this->assertSame(RoutineRequest::PENDING, $other->fresh()->status);
    }

    public function test_requests_tab_is_the_default_while_there_are_pending_requests(): void
    {
        $trainer = $this->user($this->iron, Role::Trainer);
        $laura = $this->user($this->iron, Role::Member, ['name' => 'Laura']);
        $this->pendingRequest($this->titan, $this->user($this->titan)); // otro gimnasio

        $this->actingAs($trainer)->get($this->url('/panel/rutinas'))
            ->assertInertia(fn (Assert $page) => $page->component('Gym/Routines/Index')->where('tab', 'plantillas')->where('pendingCount', 0));

        $this->pendingRequest($this->iron, $laura, 'single');

        $this->actingAs($trainer)->get($this->url('/panel/rutinas'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'solicitudes')
                ->where('pendingCount', 1)
                ->has('requests', 1)
                ->where('requests.0.user.name', 'Laura')
                ->where('requests.0.kind', 'single'));
    }

    public function test_creating_from_a_request_assigns_it_and_marks_it_done(): void
    {
        $this->freeze('2026-11-16 09:00');
        $trainer = $this->user($this->iron, Role::Trainer);
        $member = $this->user($this->iron);
        $exercise = $this->exercise($this->iron);
        $request = $this->pendingRequest($this->iron, $member, 'single');

        $this->actingAs($trainer)->get($this->url("/panel/rutinas/nueva?solicitud={$request->id}"))
            ->assertInertia(fn (Assert $page) => $page
                ->where('initial.type', 'single')
                ->where('initial.member_id', $member->id)
                ->where('initial.request_id', $request->id)
                ->where('initial.goal', 'Bajar de peso')
                ->where('request.id', $request->id));

        $this->actingAs($trainer)->post($this->url('/panel/rutinas'), [
            'name' => 'Sesión de hoy', 'type' => 'single', 'target' => 'member', 'member_id' => $member->id,
            'scheduled_for' => '2026-11-16', 'request_id' => $request->id,
            'items' => [['exercise_id' => $exercise->id, 'sets' => 3, 'reps' => '12']],
        ])->assertRedirect('/panel/rutinas?tab=solicitudes');

        $routine = Routine::withoutGlobalScopes()->sole();
        $request->refresh();
        $this->assertSame(RoutineRequest::DONE, $request->status);
        $this->assertSame($routine->id, $request->routine_id);
        $this->assertSame($trainer->id, $request->handled_by);
        $this->assertNotNull($request->handled_at);
        $this->assertSame($member->id, RoutineAssignment::withoutGlobalScopes()->where('routine_id', $routine->id)->value('user_id'));
    }

    public function test_request_must_match_the_member(): void
    {
        $trainer = $this->user($this->iron, Role::Trainer);
        $request = $this->pendingRequest($this->iron, $this->user($this->iron));
        $other = $this->user($this->iron);
        $foreignRequest = $this->pendingRequest($this->titan, $this->user($this->titan));

        $this->actingAs($trainer)->post($this->url('/panel/rutinas'), [
            'name' => 'X', 'type' => 'weekly', 'target' => 'member', 'member_id' => $other->id, 'request_id' => $request->id,
        ])->assertSessionHasErrors('member_id');

        $this->actingAs($trainer)->post($this->url('/panel/rutinas'), [
            'name' => 'X', 'type' => 'weekly', 'target' => 'member', 'member_id' => $other->id, 'request_id' => $foreignRequest->id,
        ])->assertSessionHasErrors('request_id');

        $this->assertSame(0, Routine::withoutGlobalScopes()->count());
        $this->assertSame(RoutineRequest::PENDING, $request->fresh()->status);
    }

    public function test_assign_existing_duplicates_the_template(): void
    {
        $this->freeze('2026-11-16 09:00');
        $trainer = $this->user($this->iron, Role::Trainer);
        $member = $this->user($this->iron);
        $template = $this->template($this->iron, 'weekly', ['name' => 'Hipertrofia']);
        $this->addItem($this->iron, $template, $this->exercise($this->iron));
        $request = $this->pendingRequest($this->iron, $member);

        $this->actingAs($trainer)->post($this->url("/panel/rutinas/solicitudes/{$request->id}/asignar"), ['template_id' => $template->id])
            ->assertRedirect('/panel/rutinas?tab=solicitudes')->assertSessionHas('success');

        $copy = Routine::withoutGlobalScopes()->where('is_template', false)->sole();
        $this->assertSame('Hipertrofia', $copy->name);
        $this->assertSame($member->id, $copy->member_id);
        $this->assertSame($trainer->id, $copy->created_by);
        $this->assertSame(1, $copy->items()->count());
        $this->assertTrue($template->fresh()->is_template);

        $request->refresh();
        $this->assertSame(RoutineRequest::DONE, $request->status);
        $this->assertSame($copy->id, $request->routine_id);
        $assignment = RoutineAssignment::withoutGlobalScopes()->sole();
        $this->assertSame($copy->id, $assignment->routine_id);
        $this->assertTrue($assignment->active);
        $this->assertNull($assignment->scheduled_for);
    }

    public function test_assign_existing_rejects_personal_routines_and_other_gyms(): void
    {
        $trainer = $this->user($this->iron, Role::Trainer);
        $member = $this->user($this->iron);
        $request = $this->pendingRequest($this->iron, $member);
        $personal = $this->personal($this->iron, $this->user($this->iron), $trainer);
        $foreign = $this->template($this->titan);

        foreach ([$personal, $foreign] as $routine) {
            $this->actingAs($trainer)->post($this->url("/panel/rutinas/solicitudes/{$request->id}/asignar"), ['template_id' => $routine->id])
                ->assertSessionHasErrors('template_id');
        }

        $this->assertSame(RoutineRequest::PENDING, $request->fresh()->status);
    }

    public function test_dismiss_cancels_the_request(): void
    {
        $admin = $this->user($this->iron, Role::GymAdmin);
        $request = $this->pendingRequest($this->iron, $this->user($this->iron));

        $this->actingAs($admin)->post($this->url("/panel/rutinas/solicitudes/{$request->id}/descartar"))->assertRedirect();

        $this->assertSame(RoutineRequest::CANCELLED, $request->fresh()->status);
        $this->assertSame($admin->id, $request->fresh()->handled_by);
    }

    public function test_members_cannot_handle_requests(): void
    {
        $member = $this->user($this->iron);
        $request = $this->pendingRequest($this->iron, $member);
        $template = $this->template($this->iron);

        $this->actingAs($member)->post($this->url("/panel/rutinas/solicitudes/{$request->id}/descartar"))->assertForbidden();
        $this->actingAs($member)->post($this->url("/panel/rutinas/solicitudes/{$request->id}/asignar"), ['template_id' => $template->id])
            ->assertForbidden();
        $this->assertSame(RoutineRequest::PENDING, $request->fresh()->status);
    }
}
