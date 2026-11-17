<?php

namespace Tests\Feature\Membership;

use App\Enums\Role;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

class MemberManagementTest extends MembershipTestCase
{
    private function newMember(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Carlos Pérez',
            'email' => 'carlos@example.com',
            'phone' => '5512345678',
            'plan_name' => 'Mensual',
            'months_added' => 1,
            'credits_added' => 0,
            'amount' => 550,
            'method' => 'efectivo',
        ];
    }

    public function test_admin_registers_member_and_sees_password_once(): void
    {
        $response = $this->actingAs($this->ironAdmin)->post($this->url('/panel/socios'), $this->newMember());

        $membership = Membership::withoutGlobalScopes()->firstOrFail();
        $response->assertRedirect("/panel/socios/{$membership->id}");

        $user = User::withoutGlobalScopes()->findOrFail($membership->user_id);
        $this->assertSame(Role::Member, $user->role);
        $this->assertSame($this->iron->id, $user->gym_id);
        $this->assertSame($this->iron->id, $membership->gym_id);
        $this->assertSame(today()->addMonthNoOverflow()->toDateString(), $membership->expires_on->toDateString());
        $this->assertDatabaseHas('member_payments', ['user_id' => $user->id, 'amount' => 550]);

        $password = session('new_member_credentials')['password'];
        $this->assertTrue(Hash::check($password, $user->password));

        $this->actingAs($this->ironAdmin)->withSession(['new_member_credentials' => session('new_member_credentials')])
            ->get($this->url("/panel/socios/{$membership->id}"))
            ->assertInertia(fn (Assert $p) => $p->component('Gym/Members/Show')->where('credentials.password', $password));

        // Ya no se vuelve a mostrar
        $this->actingAs($this->ironAdmin)->get($this->url("/panel/socios/{$membership->id}"))
            ->assertInertia(fn (Assert $p) => $p->where('credentials', null));
    }

    public function test_role_and_gym_cannot_be_injected(): void
    {
        $this->actingAs($this->ironAdmin)->post($this->url('/panel/socios'), $this->newMember([
            'role' => 'gym_admin', 'gym_id' => $this->titan->id,
        ]));

        $user = User::withoutGlobalScopes()->where('email', 'carlos@example.com')->firstOrFail();
        $this->assertSame(Role::Member, $user->role);
        $this->assertSame($this->iron->id, $user->gym_id);
    }

    public function test_duplicate_email_in_same_gym_fails_but_other_gym_is_allowed(): void
    {
        $this->member($this->iron, [], ['email' => 'repetido@example.com']);

        $this->actingAs($this->ironAdmin)
            ->post($this->url('/panel/socios'), $this->newMember(['email' => 'Repetido@example.com']))
            ->assertSessionHasErrors(['email' => 'Ya existe un usuario con este correo en el gimnasio.']);

        $this->actingAs($this->titanAdmin)
            ->post($this->url('/panel/socios', 'titan'), $this->newMember(['email' => 'repetido@example.com']))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(2, User::withoutGlobalScopes()->where('email', 'repetido@example.com')->count());
    }

    public function test_trainer_cannot_see_members(): void
    {
        $trainer = User::factory()->forGym($this->iron)->role(Role::Trainer)->create();
        $m = $this->member($this->iron);

        $this->actingAs($trainer)->get($this->url('/panel/socios'))->assertForbidden();
        $this->actingAs($trainer)->get($this->url("/panel/socios/{$m->id}"))->assertForbidden();
        $this->actingAs($trainer)->get($this->url('/panel/escaner'))->assertForbidden();
    }

    public function test_member_from_another_gym_is_not_found(): void
    {
        $titanMember = $this->member($this->titan);

        $this->actingAs($this->ironAdmin)->get($this->url("/panel/socios/{$titanMember->id}"))->assertNotFound();
        $this->actingAs($this->ironAdmin)
            ->post($this->url("/panel/socios/{$titanMember->id}/pagos"), ['amount' => 1, 'method' => 'efectivo', 'months_added' => 1])
            ->assertNotFound();
    }

    public function test_index_search_and_status_filter_are_scoped_to_gym(): void
    {
        $this->member($this->iron, ['expires_on' => today()->addDays(30)], ['name' => 'Laura Activa']);
        $this->member($this->iron, ['expires_on' => today()->addDays(2)], ['name' => 'Laura Porvencer']);
        $this->member($this->iron, ['expires_on' => today()->subDays(2)], ['name' => 'Laura Vencida']);
        $this->member($this->titan, ['expires_on' => today()->addDays(30)], ['name' => 'Laura Titan']);

        $this->actingAs($this->ironAdmin)->get($this->url('/panel/socios?q=laura'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Gym/Members/Index')->has('members.data', 3)->where('counts.all', 3));

        $this->actingAs($this->ironAdmin)->get($this->url('/panel/socios?estado=expired'))
            ->assertInertia(fn (Assert $p) => $p->has('members.data', 1)->where('members.data.0.name', 'Laura Vencida'));

        $this->actingAs($this->ironAdmin)->get($this->url('/panel/socios?estado=expiring'))
            ->assertInertia(fn (Assert $p) => $p->has('members.data', 1)->where('members.data.0.status', 'expiring'));

        $this->actingAs($this->ironAdmin)->getJson($this->url('/panel/escaner/buscar?q=laura'))
            ->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_update_member(): void
    {
        $m = $this->member($this->iron, ['expires_on' => today()], ['name' => 'Viejo']);

        $this->actingAs($this->ironAdmin)->put($this->url("/panel/socios/{$m->id}"), [
            'name' => 'Nuevo Nombre', 'email' => 'nuevo@example.com', 'phone' => '5511112222',
            'plan_name' => 'Anual', 'expires_on' => today()->addYear()->toDateString(), 'credits' => 3,
        ])->assertRedirect("/panel/socios/{$m->id}")->assertSessionHasNoErrors();

        $fresh = $this->fresh($m);
        $this->assertSame('Anual', $fresh->plan_name);
        $this->assertSame(3, $fresh->credits);
        $this->assertSame('Nuevo Nombre', User::withoutGlobalScopes()->find($m->user_id)->name);
    }

    public function test_member_sees_own_credential(): void
    {
        $m = $this->member($this->iron, ['expires_on' => today()->addDays(20)]);
        $member = User::withoutGlobalScopes()->find($m->user_id);

        $this->actingAs($member)->get($this->url('/app/credencial'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Member/Credential')
                ->where('qr', $this->payload($m))
                ->where('membership.status', 'active')
                ->where('membership.daysRemaining', 20));

        $this->actingAs($this->ironAdmin)->get($this->url('/app/credencial'))->assertForbidden();
    }
}
