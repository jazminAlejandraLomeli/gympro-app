<?php

namespace Tests\Feature\Tenancy;

use App\Enums\Role;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Subdominios, sesión, route model binding (IDOR) y roles vía HTTP. */
class TenantHttpTest extends TestCase
{
    use RefreshDatabase;

    private Gym $iron;

    private Gym $titan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->iron = Gym::factory()->create(['slug' => 'irondemo']);
        $this->iron->settings->update(['primary_color' => '#facc15']);
        $this->titan = Gym::factory()->create(['slug' => 'titan']);
        $this->titan->settings->update(['primary_color' => '#dc2626']);

        // Rutas solo para la prueba
        Route::middleware('web')->get('/_test/users/{user}', fn (User $user) => $user->name);
        Route::middleware('web')->get('/_test/me', fn () => ['id' => auth()->id()]);
        Route::middleware(['web', 'role:gym_admin'])->get('/_test/admin', fn () => 'ok');
    }

    private function url(?string $slug, string $path = '/'): string
    {
        return 'http://'.($slug ? "{$slug}." : '').'localhost'.$path;
    }

    /** Inicia sesión "de verdad" (por id en sesión), para que la búsqueda pase por el Global Scope. */
    private function loginAs(User $user): static
    {
        $this->freshRequest();

        return $this->withSession([auth()->guard('web')->getName() => $user->id]);
    }

    /**
     * En pruebas la app no se reinicia entre requests y el guard
     * recuerda al usuario anterior; en producción cada request es nuevo.
     */
    private function freshRequest(): static
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();

        return $this;
    }

    private function userIn(Gym $gym, Role $role = Role::Member): User
    {
        return User::factory()->forGym($gym)->role($role)->create();
    }

    public function test_each_subdomain_gets_its_own_brand(): void
    {
        $this->get($this->url('irondemo', '/login'))
            ->assertOk()
            ->assertSee('--brand-primary: #facc15', false)
            ->assertInertia(fn (Assert $page) => $page->where('brand.primary', '#facc15'));

        $this->get($this->url('titan', '/login'))
            ->assertSee('--brand-primary: #dc2626', false);
    }

    public function test_central_and_reserved_domains_use_default_brand(): void
    {
        $default = config('gympro.default_brand.primary_color');

        $this->get($this->url(null))->assertInertia(fn (Assert $p) => $p->where('brand.primary', $default));
        $this->get($this->url('www', '/login'))->assertInertia(fn (Assert $p) => $p->where('brand.primary', $default));
    }

    public function test_unknown_or_invalid_subdomain_is_404(): void
    {
        $this->get($this->url('noexiste'))->assertNotFound();
        $this->get($this->url('a.b'))->assertNotFound();
    }

    public function test_suspended_gym_is_blocked(): void
    {
        Gym::factory()->suspended()->create(['slug' => 'moroso']);

        $this->get($this->url('moroso'))->assertForbidden();
    }

    public function test_brand_cache_is_invalidated_when_settings_change(): void
    {
        $this->get($this->url('irondemo', '/login'))->assertSee('#facc15', false);

        $this->iron->settings->update(['primary_color' => '#22c55e']);

        $this->get($this->url('irondemo', '/login'))->assertSee('--brand-primary: #22c55e', false);
    }

    public function test_session_of_one_gym_is_not_valid_on_another(): void
    {
        $member = $this->userIn($this->iron);

        $this->loginAs($member)->get($this->url('irondemo', '/_test/me'))->assertExactJson(['id' => $member->id]);

        $this->loginAs($member)->get($this->url('titan', '/_test/me'))->assertExactJson(['id' => null]);
    }

    public function test_route_model_binding_blocks_other_gyms_records(): void
    {
        $admin = $this->userIn($this->iron, Role::GymAdmin);
        $titanMember = $this->userIn($this->titan);
        $ironMember = $this->userIn($this->iron);

        $this->loginAs($admin)->get($this->url('irondemo', "/_test/users/{$ironMember->id}"))->assertOk();
        $this->loginAs($admin)->get($this->url('irondemo', "/_test/users/{$titanMember->id}"))->assertNotFound();
    }

    public function test_super_admin_on_central_domain_sees_all_gyms(): void
    {
        $super = User::factory()->superAdmin()->create();
        $titanMember = $this->userIn($this->titan);

        $this->loginAs($super)->get($this->url(null, "/_test/users/{$titanMember->id}"))
            ->assertOk()->assertSee($titanMember->name);
    }

    public function test_anonymous_on_central_domain_sees_no_gym_records(): void
    {
        $titanMember = $this->userIn($this->titan);

        $this->freshRequest()->get($this->url(null, "/_test/users/{$titanMember->id}"))->assertNotFound();
    }

    public function test_role_middleware(): void
    {
        $this->loginAs($this->userIn($this->iron))->get($this->url('irondemo', '/_test/admin'))->assertForbidden();
        $this->loginAs($this->userIn($this->iron, Role::Trainer))->get($this->url('irondemo', '/_test/admin'))->assertForbidden();
        $this->loginAs($this->userIn($this->iron, Role::GymAdmin))->get($this->url('irondemo', '/_test/admin'))->assertOk();
        $this->freshRequest()->get($this->url('irondemo', '/_test/admin'))->assertUnauthorized();
    }

    public function test_gates_by_role(): void
    {
        $trainer = $this->userIn($this->iron, Role::Trainer);
        $member = $this->userIn($this->iron);
        $super = User::factory()->superAdmin()->create();

        $this->assertTrue(Gate::forUser($trainer)->allows('manage-training'));
        $this->assertFalse(Gate::forUser($trainer)->allows('operate-front-desk'));
        $this->assertFalse(Gate::forUser($member)->allows('manage-training'));
        $this->assertTrue(Gate::forUser($member)->allows('use-member-portal'));
        $this->assertFalse(Gate::forUser($member)->allows('access-saas-admin'));
        $this->assertTrue(Gate::forUser($super)->allows('access-saas-admin'));
    }
}
