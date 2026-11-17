<?php

namespace Tests\Feature\Membership;

use App\Enums\Role;
use App\Models\MemberPayment;
use App\Models\Membership;
use App\Models\User;
use App\Tenancy\TenantContext;
use Database\Seeders\Demo\MembershipDemoSeeder;

class MembershipDemoSeederTest extends MembershipTestCase
{
    public function test_seeds_varied_memberships(): void
    {
        $tenant = app(TenantContext::class);

        $tenant->runAs($this->iron, function () {
            User::factory()->role(Role::Trainer)->create();
            User::factory()->create(['email' => 'socio@demo.test']);
            User::factory()->count(5)->create();

            app(MembershipDemoSeeder::class)->seedGym($this->iron);

            $socio = Membership::query()->whereRelation('user', 'email', 'socio@demo.test')->firstOrFail();
            $this->assertSame(20, $socio->daysRemaining());
            $this->assertTrue($socio->isActive());

            $statuses = Membership::query()->get()->map->status()->unique()->sort()->values()->all();
            $this->assertSame(['active', 'expired', 'expiring'], $statuses);
            $this->assertSame(6, Membership::query()->count());
            $this->assertTrue(Membership::query()->where('credits', '>', 0)->where('expires_on', '<', today())->exists());
            $this->assertGreaterThan(0, MemberPayment::query()->count());
        });
    }
}
