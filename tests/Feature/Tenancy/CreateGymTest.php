<?php

namespace Tests\Feature\Tenancy;

use App\Actions\Gyms\CreateGym;
use App\Enums\Role;
use App\Models\Gym;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateGymTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_gym_brand_and_admin_together(): void
    {
        $gym = app(CreateGym::class)->handle(
            ['name' => 'Power House', 'primary_color' => '#2563eb'],
            ['name' => 'Laura', 'email' => 'laura@power.test', 'password' => 'secret-123'],
        );

        $this->assertSame('power-house', $gym->slug);
        $this->assertTrue($gym->isActive());
        $this->assertSame('#2563eb', $gym->settings->primary_color);
        $this->assertSame('Power House', $gym->settings->brand_name);

        $admin = app(TenantContext::class)->runAs($gym, fn () => User::sole());
        $this->assertSame(Role::GymAdmin, $admin->role);
        $this->assertSame($gym->id, $admin->gym_id);
        $this->assertTrue(Hash::check('secret-123', $admin->password));
    }

    public function test_nothing_is_saved_if_a_step_fails(): void
    {
        app(CreateGym::class)->handle(
            ['name' => 'Uno', 'slug' => 'dup'],
            ['name' => 'A', 'email' => 'a@test.test', 'password' => 'secret-123'],
        );

        try {
            // Slug repetido: falla al crear el gym
            app(CreateGym::class)->handle(
                ['name' => 'Dos', 'slug' => 'dup'],
                ['name' => 'B', 'email' => 'b@test.test', 'password' => 'secret-123'],
            );
        } catch (QueryException) {
        }

        $this->assertSame(1, Gym::count());
        $this->assertSame(1, app(TenantContext::class)->withoutScope(fn () => User::count()));
    }
}
