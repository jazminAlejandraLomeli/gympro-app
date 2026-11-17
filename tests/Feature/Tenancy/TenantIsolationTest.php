<?php

namespace Tests\Feature\Tenancy;

use App\Enums\Role;
use App\Models\Gym;
use App\Models\User;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantViolation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Global Scope y reglas de escritura: cero fugas entre gimnasios. */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Gym $iron;

    private Gym $titan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->iron = Gym::factory()->create(['slug' => 'irondemo']);
        $this->titan = Gym::factory()->create(['slug' => 'titan']);

        User::factory()->count(3)->forGym($this->iron)->create();
        User::factory()->count(2)->forGym($this->titan)->create();
        User::factory()->superAdmin()->create();
    }

    private function tenant(): TenantContext
    {
        return app(TenantContext::class);
    }

    public function test_queries_only_see_the_active_gym(): void
    {
        $ids = $this->tenant()->runAs($this->iron, fn () => User::pluck('gym_id')->unique()->all());

        $this->assertSame([$this->iron->id], $ids);
        $this->assertSame(2, $this->tenant()->runAs($this->titan, fn () => User::count()));
    }

    public function test_without_gym_only_central_users_are_visible(): void
    {
        // Falla "cerrado": sin gimnasio no se ven socios de nadie
        $this->assertSame([Role::SuperAdmin], User::pluck('role')->all());
    }

    public function test_find_by_id_of_another_gym_returns_null(): void
    {
        $titanUser = $this->tenant()->runAs($this->titan, fn () => User::first());

        $this->assertNull($this->tenant()->runAs($this->iron, fn () => User::find($titanUser->id)));
    }

    public function test_super_admin_bypass_sees_everything(): void
    {
        $this->assertSame(6, $this->tenant()->withoutScope(fn () => User::count()));
    }

    public function test_new_records_inherit_the_active_gym(): void
    {
        $user = $this->tenant()->runAs($this->iron, fn () => User::factory()->create());

        $this->assertSame($this->iron->id, $user->gym_id);
    }

    public function test_cannot_create_a_record_for_another_gym(): void
    {
        $this->expectException(TenantViolation::class);

        $this->tenant()->runAs($this->iron, fn () => User::factory()->forGym($this->titan)->create());
    }

    public function test_web_requests_cannot_create_records_without_a_gym(): void
    {
        $this->tenant()->enforceStrictMode();

        $this->expectException(TenantViolation::class);

        User::factory()->create(['gym_id' => null]);
    }

    public function test_web_requests_cannot_move_a_record_to_another_gym(): void
    {
        $this->tenant()->enforceStrictMode();

        $this->expectException(TenantViolation::class);

        $this->tenant()->runAs($this->iron, function () {
            $user = User::first();
            $user->gym_id = $this->titan->id;
            $user->save();
        });
    }

    public function test_gym_id_and_role_cannot_be_mass_assigned(): void
    {
        $user = $this->tenant()->runAs($this->iron, fn () => User::create([
            'name' => 'Intruso',
            'email' => 'intruso@test.test',
            'password' => 'secret-123',
            'role' => 'super_admin',
            'gym_id' => $this->titan->id,
        ]));

        $this->assertSame($this->iron->id, $user->gym_id);
        $this->assertSame(Role::Member, $user->fresh()->role);
    }

    public function test_same_email_can_exist_in_two_gyms(): void
    {
        $this->tenant()->runAs($this->iron, fn () => User::factory()->create(['email' => 'ana@test.test']));
        $this->tenant()->runAs($this->titan, fn () => User::factory()->create(['email' => 'ana@test.test']));

        $this->assertSame(2, $this->tenant()->withoutScope(fn () => User::where('email', 'ana@test.test')->count()));
    }

    public function test_cache_keys_are_namespaced_by_gym(): void
    {
        $key = $this->tenant()->runAs($this->iron, fn (Gym $gym) => $this->tenant()->key('member', 15, 'status'));

        $this->assertSame("gym:{$this->iron->id}:member:15:status", $key);
    }
}
