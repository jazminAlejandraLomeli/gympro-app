<?php

namespace Tests\Feature\Admin;

use App\Enums\GymStatus;
use App\Enums\Role;
use App\Models\Gym;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;

class GymManagementTest extends AdminTestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Fuerza Norte',
            'slug' => 'fuerza-norte',
            'primary_color' => '#FF0000',
            'secondary_color' => '#111827',
            'admin_name' => 'Laura Pérez',
            'admin_email' => 'Laura@Fuerza.test',
            'admin_password' => 'secreto123',
        ], $overrides);
    }

    public function test_super_admin_creates_a_gym_with_its_admin(): void
    {
        $response = $this->actingAs($this->super)->post($this->url('/admin/gimnasios'), $this->payload());

        $gym = Gym::query()->where('slug', 'fuerza-norte')->firstOrFail();
        $response->assertRedirect(route('admin.gyms.show', $gym))->assertSessionHas('success');

        $this->assertTrue($gym->isActive());
        $this->assertSame('#ff0000', $gym->settings->primary_color);

        $admin = $gym->users()->first();
        $this->assertSame(Role::GymAdmin, $admin->role);
        $this->assertSame('laura@fuerza.test', $admin->email);
    }

    public static function invalidSlugs(): array
    {
        return [
            'mayúsculas' => ['Fuerza'],
            'espacios' => ['fuerza norte'],
            'acentos' => ['fuérza'],
            'guion al inicio' => ['-fuerza'],
            'guion al final' => ['fuerza-'],
            'doble guion' => ['fuerza--norte'],
            'punto' => ['fuerza.norte'],
            'reservado' => ['admin'],
            'reservado www' => ['www'],
            'muy corto' => ['ab'],
            'muy largo' => [str_repeat('a', 64)],
        ];
    }

    #[DataProvider('invalidSlugs')]
    public function test_slug_is_validated(string $slug): void
    {
        $this->actingAs($this->super)
            ->post($this->url('/admin/gimnasios'), $this->payload(['slug' => $slug]))
            ->assertSessionHasErrors('slug');

        $this->assertSame(0, Gym::query()->count());
    }

    public function test_slug_must_be_unique(): void
    {
        Gym::factory()->create(['slug' => 'fuerza-norte']);

        $this->actingAs($this->super)
            ->post($this->url('/admin/gimnasios'), $this->payload())
            ->assertSessionHasErrors(['slug' => 'Ese subdominio ya lo usa otro gimnasio.']);
    }

    public function test_list_filters_by_search_status_and_billing(): void
    {
        Gym::factory()->create(['name' => 'Iron Demo', 'slug' => 'irondemo', 'saas_paid_until' => today()->addMonths(2)]);
        Gym::factory()->create(['name' => 'Titan Fit', 'slug' => 'titan', 'saas_paid_until' => today()->addDays(3)]);
        Gym::factory()->suspended()->create(['name' => 'Pulso Box', 'slug' => 'pulso', 'saas_paid_until' => today()->subDays(10)]);

        $names = fn (string $query) => collect(
            $this->actingAs($this->super)->get($this->url('/admin/gimnasios'.$query))
                ->assertOk()->viewData('page')['props']['gyms']['data']
        )->pluck('name')->all();

        $this->assertSame(['Titan Fit'], $names('?q=tit'));
        $this->assertSame(['Pulso Box'], $names('?status=suspended'));
        $this->assertSame(['Titan Fit'], $names('?billing=por_vencer'));
        $this->assertSame(['Pulso Box'], $names('?billing=vencidos'));
        $this->assertSame(['Iron Demo'], $names('?billing=al_dia'));

        $this->actingAs($this->super)->get($this->url('/admin/gimnasios'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Gyms/Index')
                ->where('baseDomain', 'localhost')
                ->where('gyms.data.0.host', 'irondemo.localhost'));
    }

    public function test_detail_shows_referrals_and_updates_saas_data(): void
    {
        $iron = Gym::factory()->create(['slug' => 'irondemo']);
        Gym::factory()->count(2)->create(['referred_by_gym_id' => $iron->id]);
        $titan = Gym::factory()->create(['slug' => 'titan']);

        $this->actingAs($this->super)->get($this->url("/admin/gimnasios/{$iron->id}"))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Gyms/Show')
                ->has('referrals', 2)
                ->where('gym.host', 'irondemo.localhost'));

        $this->actingAs($this->super)->put($this->url("/admin/gimnasios/{$titan->id}"), [
            'name' => 'Titan Fit',
            'saas_monthly_fee' => 2000,
            'saas_discount' => 250,
            'referred_by_gym_id' => $iron->id,
        ])->assertSessionHasNoErrors();

        $titan->refresh();
        $this->assertSame('2000.00', $titan->saas_monthly_fee);
        $this->assertSame('250.00', $titan->saas_discount);
        $this->assertSame($iron->id, $titan->referred_by_gym_id);
    }

    public function test_update_rejects_self_referral_and_discount_above_fee(): void
    {
        $gym = Gym::factory()->create();

        $this->actingAs($this->super)->put($this->url("/admin/gimnasios/{$gym->id}"), [
            'name' => 'X',
            'saas_monthly_fee' => 1000,
            'saas_discount' => 1500,
            'referred_by_gym_id' => $gym->id,
        ])->assertSessionHasErrors(['saas_discount', 'referred_by_gym_id']);
    }

    public function test_suspending_blocks_the_subdomain_and_activating_restores_it(): void
    {
        $gym = Gym::factory()->create(['slug' => 'irondemo']);
        $member = User::factory()->forGym($gym)->create();

        // Calienta el caché del subdominio
        $this->actingAs($member)->get($this->url('/app', 'irondemo'))->assertOk();

        $this->fresh()->actingAs($this->super)
            ->post($this->url("/admin/gimnasios/{$gym->id}/suspender"))
            ->assertSessionHas('success');

        $gym->refresh();
        $this->assertSame(GymStatus::Suspended, $gym->status);
        $this->assertNotNull($gym->suspended_at);

        $this->fresh()->get($this->url('/login', 'irondemo'))->assertForbidden();
        $this->fresh()->actingAs($member)->get($this->url('/app', 'irondemo'))->assertForbidden();

        $this->fresh()->actingAs($this->super)->post($this->url("/admin/gimnasios/{$gym->id}/activar"));

        $gym->refresh();
        $this->assertTrue($gym->isActive());
        $this->assertNull($gym->suspended_at);
        $this->fresh()->actingAs($member)->get($this->url('/app', 'irondemo'))->assertOk();
    }
}
