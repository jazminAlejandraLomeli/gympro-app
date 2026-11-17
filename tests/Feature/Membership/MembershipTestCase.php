<?php

namespace Tests\Feature\Membership;

use App\Enums\Role;
use App\Models\Gym;
use App\Models\Membership;
use App\Models\User;
use App\Services\Membership\QrSigner;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class MembershipTestCase extends TestCase
{
    use RefreshDatabase;

    protected Gym $iron;

    protected Gym $titan;

    protected User $ironAdmin;

    protected User $titanAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->iron = Gym::factory()->create(['slug' => 'irondemo']);
        $this->titan = Gym::factory()->create(['slug' => 'titan']);
        $this->ironAdmin = User::factory()->forGym($this->iron)->role(Role::GymAdmin)->create();
        $this->titanAdmin = User::factory()->forGym($this->titan)->role(Role::GymAdmin)->create();
    }

    protected function url(string $path, string $slug = 'irondemo'): string
    {
        return "http://{$slug}.localhost{$path}";
    }

    /** Crea un socio con membresía dentro del gimnasio indicado. */
    protected function member(Gym $gym, array $membership = [], array $user = []): Membership
    {
        return app(TenantContext::class)->runAs($gym, function () use ($gym, $membership, $user) {
            $u = User::factory()->forGym($gym)->role(Role::Member)->create($user);

            $m = new Membership($membership + ['plan_name' => 'Mensual']);
            $m->user_id = $u->id;
            $m->save();

            return $m;
        });
    }

    protected function payload(Membership $m): string
    {
        return app(QrSigner::class)->payloadFor($m);
    }

    protected function fresh(Membership $m): Membership
    {
        return Membership::withoutGlobalScopes()->findOrFail($m->id);
    }

    protected function scan(string $payload, ?User $as = null, string $slug = 'irondemo')
    {
        return $this->actingAs($as ?? $this->ironAdmin)
            ->postJson($this->url('/panel/asistencia/escanear', $slug), ['payload' => $payload]);
    }
}
