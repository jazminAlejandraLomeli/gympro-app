<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\Gym;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;

class AccessTest extends AdminTestCase
{
    public static function pages(): array
    {
        return [
            ['/admin'], ['/admin/gimnasios'], ['/admin/finanzas'], ['/admin/sistema'], ['/admin/errores'],
        ];
    }

    #[DataProvider('pages')]
    public function test_super_admin_sees_every_page(string $path): void
    {
        Gym::factory()->create(['slug' => 'irondemo']);

        $this->actingAs($this->super)->get($this->url($path))->assertOk();
    }

    #[DataProvider('pages')]
    public function test_guest_is_sent_to_login(string $path): void
    {
        $this->get($this->url($path))->assertRedirect(route('login'));
    }

    public function test_gym_admin_cannot_reach_the_saas_panel(): void
    {
        $gym = Gym::factory()->create(['slug' => 'irondemo']);
        $admin = User::factory()->forGym($gym)->role(Role::GymAdmin)->create();

        // En su subdominio, /admin no existe
        $this->actingAs($admin)->get($this->url('/admin', 'irondemo'))->assertNotFound();
        $this->actingAs($admin)->get($this->url('/admin/gimnasios', 'irondemo'))->assertNotFound();

        // Aun con la sesión en el dominio central, el rol no alcanza
        $this->fresh()->actingAs($admin)->get($this->url('/admin/gimnasios'))->assertForbidden();
        $this->fresh()->actingAs($admin)->post($this->url('/admin/gimnasios/'.$gym->id.'/suspender'))->assertForbidden();
        $this->assertTrue($gym->fresh()->isActive());
    }

    public function test_admin_routes_do_not_exist_on_a_gym_subdomain(): void
    {
        $gym = Gym::factory()->create(['slug' => 'irondemo']);

        $this->actingAs($this->super)->get($this->url('/admin/finanzas', 'irondemo'))->assertNotFound();
        $this->actingAs($this->super)->post($this->url("/admin/gimnasios/{$gym->id}/suspender", 'irondemo'))->assertNotFound();
    }
}
