<?php

namespace Tests\Feature\Announcements;

use App\Enums\AnnouncementType;
use App\Models\Announcement;
use App\Models\Gym;
use App\Models\User;
use App\Services\Announcements\VisibleAnnouncements;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AnnouncementVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-17 10:00:00'));
        $this->gym = Gym::factory()->create(['slug' => 'irondemo']);
        $this->member = User::factory()->forGym($this->gym)->create();
        app(TenantContext::class)->set($this->gym);
    }

    private function announcement(array $attributes): Announcement
    {
        $a = new Announcement(array_merge([
            'type' => AnnouncementType::Operativo,
            'title' => 'Aviso',
            'body' => 'Contenido',
            'starts_at' => now()->subHour(),
            'expires_at' => null,
            'pinned' => false,
        ], $attributes));
        $a->save();

        return $a;
    }

    private function visibleTitles(): array
    {
        return array_column(VisibleAnnouncements::forMemberHome($this->member)['announcements'], 'title');
    }

    public function test_scope_respects_start_and_expiry_dates(): void
    {
        $this->announcement(['title' => 'Vigente', 'expires_at' => now()->addDay()]);
        $this->announcement(['title' => 'Programado', 'starts_at' => now()->addHours(2)]);
        $this->announcement(['title' => 'Expirado', 'starts_at' => now()->subDays(3), 'expires_at' => now()->subDay()]);
        $this->announcement(['title' => 'Sin fin', 'starts_at' => now()->subDays(30)]);

        $this->assertEqualsCanonicalizing(['Vigente', 'Sin fin'], Announcement::visible()->pluck('title')->all());

        // Pasan 3 horas: el programado ya está vigente
        $this->travel(3)->hours();
        $this->assertEqualsCanonicalizing(['Vigente', 'Sin fin', 'Programado'], Announcement::visible()->pluck('title')->all());

        // Pasan 2 días: el "Vigente" expiró
        $this->travel(2)->days();
        $this->assertEqualsCanonicalizing(['Sin fin', 'Programado'], Announcement::visible()->pluck('title')->all());
    }

    public function test_expiry_boundary_is_exclusive_and_pinned_come_first(): void
    {
        $this->announcement(['title' => 'Normal', 'starts_at' => now()->subMinute()]);
        $this->announcement(['title' => 'Fijado', 'starts_at' => now()->subDays(5), 'pinned' => true]);
        $this->announcement(['title' => 'Expira ya', 'expires_at' => now()]);

        $this->assertSame(['Fijado', 'Normal'], Announcement::visible()->pluck('title')->all());
    }

    public function test_status_labels(): void
    {
        $this->assertSame('vigente', $this->announcement(['expires_at' => now()->addDay()])->status());
        $this->assertSame('programado', $this->announcement(['starts_at' => now()->addDay()])->status());
        $this->assertSame('expirado', $this->announcement(['starts_at' => now()->subDays(2), 'expires_at' => now()->subDay()])->status());
    }

    public function test_member_home_props_are_cached_and_invalidated_on_save_and_delete(): void
    {
        $first = $this->announcement(['title' => 'Primero']);
        $key = app(TenantContext::class)->key('announcements', 'visible');

        $this->assertSame(['Primero'], $this->visibleTitles());
        $this->assertTrue(Cache::has($key));

        // Un cambio que no pasa por el modelo NO se ve: viene de la caché
        Announcement::query()->whereKey($first->id)->toBase()->update(['title' => 'Cambiado a mano']);
        $this->assertSame(['Primero'], $this->visibleTitles());

        // Guardar con el modelo invalida
        $this->announcement(['title' => 'Segundo', 'pinned' => true]);
        $this->assertFalse(Cache::has($key));
        $this->assertSame(['Segundo', 'Cambiado a mano'], $this->visibleTitles());

        // Borrar invalida
        $first->delete();
        $this->assertFalse(Cache::has($key));
        $this->assertSame(['Segundo'], $this->visibleTitles());
    }

    public function test_cache_never_outlives_the_next_start_or_expiry(): void
    {
        $this->announcement(['title' => 'Promo flash', 'starts_at' => now()->addMinutes(2), 'type' => AnnouncementType::Promocion]);

        $this->assertSame([], $this->visibleTitles());

        // A los 2 minutos aparece sola, sin tocar avisos ni esperar los 5 minutos
        $this->travel(2)->minutes();
        $this->assertSame(['Promo flash'], $this->visibleTitles());

        $props = VisibleAnnouncements::forMemberHome($this->member)['announcements'][0];
        $this->assertSame('promocion', $props['type']);
        $this->assertSame('Promoción', $props['typeLabel']);
    }

    public function test_cache_is_isolated_per_gym(): void
    {
        $this->announcement(['title' => 'Iron']);
        $this->assertSame(['Iron'], $this->visibleTitles());

        $tenant = app(TenantContext::class);
        $tenant->forget();
        $other = Gym::factory()->create();
        $otherMember = User::factory()->forGym($other)->create();
        $tenant->set($this->gym);

        $tenant->runAs($other, function () use ($otherMember) {
            $this->assertSame([], VisibleAnnouncements::forMemberHome($otherMember)['announcements']);
        });
    }
}
