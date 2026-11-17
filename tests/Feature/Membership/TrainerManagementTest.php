<?php

namespace Tests\Feature\Membership;

use App\Enums\Role;
use App\Models\User;

class TrainerManagementTest extends MembershipTestCase
{
    public function test_admin_manages_trainers(): void
    {
        $this->actingAs($this->ironAdmin)->post($this->url('/panel/entrenadores'), [
            'name' => 'Coach Uno', 'email' => 'coach1@example.com', 'password' => 'secreto123', 'role' => 'gym_admin',
        ])->assertRedirect('/panel/entrenadores');

        $trainer = User::withoutGlobalScopes()->where('email', 'coach1@example.com')->firstOrFail();
        $this->assertSame(Role::Trainer, $trainer->role);
        $this->assertSame($this->iron->id, $trainer->gym_id);

        $this->actingAs($this->ironAdmin)->put($this->url("/panel/entrenadores/{$trainer->id}"), [
            'name' => 'Coach Editado', 'email' => 'coach1@example.com', 'password' => '',
        ])->assertRedirect('/panel/entrenadores');
        $this->assertSame('Coach Editado', $trainer->fresh()->name);

        $this->actingAs($this->ironAdmin)->delete($this->url("/panel/entrenadores/{$trainer->id}"))->assertRedirect('/panel/entrenadores');
        $this->assertNull(User::withoutGlobalScopes()->find($trainer->id));
    }

    public function test_trainer_endpoints_cannot_touch_members_or_other_gyms(): void
    {
        $member = User::factory()->forGym($this->iron)->role(Role::Member)->create();
        $titanTrainer = User::factory()->forGym($this->titan)->role(Role::Trainer)->create();

        $this->actingAs($this->ironAdmin)->delete($this->url("/panel/entrenadores/{$member->id}"))->assertNotFound();
        $this->actingAs($this->ironAdmin)->delete($this->url("/panel/entrenadores/{$titanTrainer->id}"))->assertNotFound();
        $this->assertNotNull(User::withoutGlobalScopes()->find($member->id));
    }

    public function test_trainer_cannot_manage_trainers(): void
    {
        $trainer = User::factory()->forGym($this->iron)->role(Role::Trainer)->create();

        $this->actingAs($trainer)->get($this->url('/panel/entrenadores'))->assertForbidden();
    }
}
