<?php

namespace Tests\Feature\Announcements;

use App\Enums\Role;
use App\Models\Announcement;
use App\Models\Gym;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AnnouncementManagementTest extends TestCase
{
    use RefreshDatabase;

    private Gym $gym;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2026-11-17 10:00:00'));
        $this->gym = Gym::factory()->create(['slug' => 'irondemo']);
        $this->admin = User::factory()->forGym($this->gym)->role(Role::GymAdmin)->create();
    }

    private function url(string $path = ''): string
    {
        return "http://irondemo.localhost/panel/avisos{$path}";
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'promocion',
            'title' => 'Hoy 2x1 en proteína',
            'body' => 'Solo en mostrador.',
            'starts_at' => '2026-11-17T08:00',
            'expires_at' => '2026-11-17T22:00',
            'pinned' => true,
        ], $overrides);
    }

    public function test_admin_creates_and_lists_with_status(): void
    {
        $this->actingAs($this->admin)->post($this->url(), $this->payload())
            ->assertRedirect($this->url())
            ->assertSessionHas('success');

        $a = Announcement::withoutGlobalScopes()->sole();
        $this->assertSame($this->gym->id, $a->gym_id);
        $this->assertSame($this->admin->id, $a->created_by);
        $this->assertTrue($a->pinned);
        $this->assertSame('2026-11-17 08:00', $a->starts_at->format('Y-m-d H:i'));

        $this->actingAs($this->admin)->get($this->url())
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Gym/Announcements/Index')
                ->where('announcements.data.0.status', 'vigente')
                ->where('announcements.data.0.title', 'Hoy 2x1 en proteína'));
    }

    public function test_expiry_must_be_after_start(): void
    {
        $this->actingAs($this->admin)
            ->post($this->url(), $this->payload(['expires_at' => '2026-11-17T07:00']))
            ->assertSessionHasErrors(['expires_at' => 'La expiración debe ser posterior al inicio.']);

        $this->assertSame(0, Announcement::withoutGlobalScopes()->count());
    }

    public function test_admin_updates_and_deletes(): void
    {
        $this->actingAs($this->admin)->post($this->url(), $this->payload());
        $a = Announcement::withoutGlobalScopes()->sole();

        $this->actingAs($this->admin)
            ->put($this->url("/{$a->id}"), $this->payload(['title' => 'Editado', 'expires_at' => '', 'pinned' => false]))
            ->assertRedirect($this->url());

        $a->refresh();
        $this->assertSame('Editado', $a->title);
        $this->assertNull($a->expires_at);
        $this->assertFalse($a->pinned);

        $this->actingAs($this->admin)->delete($this->url("/{$a->id}"))->assertRedirect($this->url());
        $this->assertSame(0, Announcement::withoutGlobalScopes()->count());
    }

    public function test_trainer_and_member_cannot_manage_announcements(): void
    {
        $trainer = User::factory()->forGym($this->gym)->role(Role::Trainer)->create();
        $member = User::factory()->forGym($this->gym)->create();

        foreach ([$trainer, $member] as $user) {
            $this->actingAs($user)->get($this->url())->assertForbidden();
            $this->actingAs($user)->post($this->url(), $this->payload())->assertForbidden();
        }

        $this->assertSame(0, Announcement::withoutGlobalScopes()->count());
    }

    public function test_cannot_edit_announcement_of_another_gym(): void
    {
        $other = Gym::factory()->create();
        $foreign = app(TenantContext::class)->runAs($other, function () {
            $a = new Announcement(['type' => 'operativo', 'title' => 'Ajeno', 'body' => 'x', 'starts_at' => now()]);
            $a->save();

            return $a;
        });

        $this->actingAs($this->admin)->get($this->url("/{$foreign->id}/editar"))->assertNotFound();
        $this->actingAs($this->admin)->delete($this->url("/{$foreign->id}"))->assertNotFound();
        $this->assertSame(1, Announcement::withoutGlobalScopes()->count());
    }
}
