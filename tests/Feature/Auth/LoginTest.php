<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private Gym $iron;

    private Gym $titan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->iron = Gym::factory()->create(['slug' => 'irondemo']);
        $this->titan = Gym::factory()->create(['slug' => 'titan']);
    }

    private function url(?string $slug, string $path): string
    {
        return 'http://'.($slug ? "{$slug}." : '').'localhost'.$path;
    }

    private function login(?string $slug, string $email, string $password = 'password')
    {
        $this->app['auth']->forgetGuards();

        return $this->post($this->url($slug, '/login'), compact('email', 'password'));
    }

    public function test_member_logs_in_on_own_gym_and_lands_on_portal(): void
    {
        $member = User::factory()->forGym($this->iron)->create(['email' => 'ana@test.test']);

        $this->login('irondemo', 'ana@test.test')->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($member);

        $this->get($this->url('irondemo', '/inicio'))->assertRedirect(route('member.home'));
    }

    public function test_credentials_from_another_gym_do_not_work(): void
    {
        User::factory()->forGym($this->iron)->create(['email' => 'ana@test.test']);

        $this->login('titan', 'ana@test.test')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_gym_users_cannot_log_in_on_central_domain(): void
    {
        User::factory()->forGym($this->iron)->role(Role::GymAdmin)->create(['email' => 'admin@test.test']);

        $this->login(null, 'admin@test.test')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_super_admin_logs_in_on_central_domain(): void
    {
        User::factory()->superAdmin()->create(['email' => 'super@test.test']);

        $this->login(null, 'super@test.test')->assertRedirect(route('home'));
        $this->assertAuthenticated();
        $this->assertSame(route('admin.dashboard'), $this->get($this->url(null, '/inicio'))->headers->get('Location'));
    }

    public function test_super_admin_cannot_log_in_on_a_gym_subdomain(): void
    {
        User::factory()->superAdmin()->create(['email' => 'super@test.test']);

        $this->login('irondemo', 'super@test.test')->assertSessionHasErrors('email');
    }

    public function test_wrong_password_is_rejected_and_rate_limited(): void
    {
        User::factory()->forGym($this->iron)->create(['email' => 'ana@test.test']);

        foreach (range(1, 5) as $i) {
            $this->login('irondemo', 'ana@test.test', 'incorrecta')->assertSessionHasErrors('email');
        }

        $this->login('irondemo', 'ana@test.test')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_roles_only_reach_their_area(): void
    {
        $member = User::factory()->forGym($this->iron)->create();
        $trainer = User::factory()->forGym($this->iron)->role(Role::Trainer)->create();

        $this->actingAs($member)->get($this->url('irondemo', '/panel'))->assertForbidden();
        $this->actingAs($member)->get($this->url('irondemo', '/app'))->assertOk();
        $this->actingAs($trainer)->get($this->url('irondemo', '/panel'))->assertOk();
        $this->actingAs($trainer)->get($this->url('irondemo', '/panel/marca'))->assertForbidden();
    }

    public function test_panel_does_not_exist_on_central_domain_and_admin_not_on_gyms(): void
    {
        $super = User::factory()->superAdmin()->create();
        $admin = User::factory()->forGym($this->iron)->role(Role::GymAdmin)->create();

        $this->actingAs($super)->get($this->url(null, '/panel'))->assertNotFound();
        $this->actingAs($admin)->get($this->url('irondemo', '/admin'))->assertNotFound();
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->get($this->url('irondemo', '/app'))->assertRedirect(route('login'));
        $this->get($this->url('irondemo', '/'))->assertRedirect(route('login'));
    }

    public function test_logout(): void
    {
        $member = User::factory()->forGym($this->iron)->create();

        $this->actingAs($member)->post($this->url('irondemo', '/logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
