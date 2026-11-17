<?php

namespace Tests\Feature\Progress;

use App\Enums\Role;
use App\Models\BodyMeasurement;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GymMeasurementTest extends TestCase
{
    use ProgressTestHelpers, RefreshDatabase;

    private Gym $iron;

    private Gym $titan;

    private User $trainer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-11-18 10:00:00');
        $this->iron = Gym::factory()->create(['slug' => 'irondemo']);
        $this->titan = Gym::factory()->create(['slug' => 'titan']);
        $this->trainer = User::factory()->forGym($this->iron)->role(Role::Trainer)->create();
    }

    private function url(string $path, string $slug = 'irondemo'): string
    {
        return "http://{$slug}.localhost{$path}";
    }

    public function test_index_lists_members_with_last_measurement_and_pending_flag(): void
    {
        $ana = $this->memberOf($this->iron, ['name' => 'Ana Ruiz']);
        $beto = $this->memberOf($this->iron, ['name' => 'Beto Díaz']);
        $carla = $this->memberOf($this->iron, ['name' => 'Carla Paz']);
        $this->memberOf($this->titan, ['name' => 'Ajeno Titan']);

        $this->measurement($this->iron, $ana, '2026-09-01', ['weight_kg' => 70]);
        $this->measurement($this->iron, $ana, '2026-11-10', ['weight_kg' => 68]); // al día
        $this->measurement($this->iron, $beto, '2026-10-19', ['weight_kg' => 90]); // 30 días: pendiente

        $this->actingAs($this->trainer)->get($this->url('/panel/valoraciones'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Gym/Measurements/Index')
                ->has('members.data', 3)
                ->where('members.data.0.name', 'Ana Ruiz')
                ->where('members.data.0.lastMeasuredOn', '2026-11-10')
                ->where('members.data.0.pending', false)
                ->where('members.data.1.name', 'Beto Díaz')
                ->where('members.data.1.pending', true)
                ->where('members.data.2.name', 'Carla Paz')
                ->where('members.data.2.lastMeasuredOn', null)
                ->where('members.data.2.pending', true)
            );

        $this->actingAs($this->trainer)->get($this->url('/panel/valoraciones?q=bet'))
            ->assertInertia(fn (Assert $page) => $page->has('members.data', 1)->where('members.data.0.id', $beto->id));

        $this->actingAs($this->trainer)->get($this->url('/panel/valoraciones?pendientes=1'))
            ->assertInertia(fn (Assert $page) => $page->has('members.data', 2)
                ->where('members.data.0.id', $beto->id)
                ->where('members.data.1.id', $carla->id));
    }

    public function test_trainer_sees_member_file_and_records_measurement(): void
    {
        $member = $this->memberOf($this->iron);
        $this->measurement($this->iron, $member, '2026-09-01', ['weight_kg' => 80]);

        $this->actingAs($this->trainer)->get($this->url("/panel/valoraciones/{$member->id}"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Gym/Measurements/Show')
                ->where('member.id', $member->id)
                ->has('progress.history', 1)
                ->where('progress.reminder.reason', 'due')
                ->where('today', '2026-11-18'));

        $this->actingAs($this->trainer)->post($this->url("/panel/valoraciones/{$member->id}"), [
            'measured_on' => '2026-11-18',
            'weight_kg' => 77.5,
            'body_fat_pct' => 22,
            'notes' => 'Buen avance',
        ])->assertRedirect()->assertSessionHas('success');

        $m = BodyMeasurement::withoutGlobalScopes()->latest('id')->first();
        $this->assertSame($member->id, $m->user_id);
        $this->assertSame($this->trainer->id, $m->recorded_by);
        $this->assertSame($this->iron->id, $m->gym_id);
    }

    public function test_gym_admin_can_record_too(): void
    {
        $admin = User::factory()->forGym($this->iron)->role(Role::GymAdmin)->create();
        $member = $this->memberOf($this->iron);

        $this->actingAs($admin)->post($this->url("/panel/valoraciones/{$member->id}"), [
            'measured_on' => '2026-11-18', 'weight_kg' => 70,
        ])->assertRedirect()->assertSessionHas('success');
    }

    public function test_trainer_cannot_touch_member_of_another_gym(): void
    {
        $foreign = $this->memberOf($this->titan);

        $this->actingAs($this->trainer)->get($this->url("/panel/valoraciones/{$foreign->id}"))->assertNotFound();
        $this->actingAs($this->trainer)->post($this->url("/panel/valoraciones/{$foreign->id}"), [
            'measured_on' => '2026-11-18', 'weight_kg' => 70,
        ])->assertNotFound();

        $this->assertSame(0, BodyMeasurement::withoutGlobalScopes()->count());
    }

    public function test_cannot_record_measurement_for_staff_user(): void
    {
        $admin = User::factory()->forGym($this->iron)->role(Role::GymAdmin)->create();

        $this->actingAs($this->trainer)->post($this->url("/panel/valoraciones/{$admin->id}"), [
            'measured_on' => '2026-11-18', 'weight_kg' => 70,
        ])->assertNotFound();
    }

    public function test_member_cannot_enter_panel(): void
    {
        $member = $this->memberOf($this->iron);

        $this->actingAs($member)->get($this->url('/panel/valoraciones'))->assertForbidden();
        $this->actingAs($member)->post($this->url("/panel/valoraciones/{$member->id}"), [
            'measured_on' => '2026-11-18', 'weight_kg' => 70,
        ])->assertForbidden();
    }
}
