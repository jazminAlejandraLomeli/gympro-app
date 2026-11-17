<?php

namespace Tests\Feature\Progress;

use App\Enums\Role;
use App\Models\BodyMeasurement;
use App\Models\Gym;
use App\Models\MemberGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MemberProgressTest extends TestCase
{
    use ProgressTestHelpers, RefreshDatabase;

    private Gym $gym;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-11-18 10:00:00');
        $this->gym = Gym::factory()->create(['slug' => 'irondemo']);
        $this->member = $this->memberOf($this->gym);
    }

    private function url(string $path): string
    {
        return "http://irondemo.localhost{$path}";
    }

    public function test_member_sees_only_their_own_progress(): void
    {
        $other = $this->memberOf($this->gym);
        $this->measurement($this->gym, $this->member, '2026-09-01', ['weight_kg' => 90]);
        $this->measurement($this->gym, $this->member, '2026-11-01', ['weight_kg' => 86]);
        $this->measurement($this->gym, $other, '2026-11-02', ['weight_kg' => 55]);
        $this->scans($this->gym, $this->member, '2026-11-16 08:00:00');
        $this->scans($this->gym, $other, '2026-11-17 08:00:00', '2026-11-18 08:00:00');

        $this->actingAs($this->member)->get($this->url('/app/progreso'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Member/Progress')
                ->where('streak.attendedThisWeek', 1)
                ->has('progress.history', 2)
                ->where('progress.history.0.weight_kg', 86)
                ->where('progress.achievements.0.text', '¡Has bajado 4 kg desde tu inicio!')
                ->where('progress.reminder', null)
                ->where('defaultGoal', 3)
            );
    }

    public function test_member_records_measurement_for_themself_only(): void
    {
        $other = $this->memberOf($this->gym);

        $this->actingAs($this->member)->post($this->url('/app/progreso/medidas'), [
            'measured_on' => '2026-11-18',
            'weight_kg' => 82.4,
            'waist_cm' => 90,
            'user_id' => $other->id, // se ignora
            'gym_id' => 999,         // se ignora
        ])->assertRedirect()->assertSessionHas('success');

        $m = BodyMeasurement::withoutGlobalScopes()->sole();
        $this->assertSame($this->member->id, $m->user_id);
        $this->assertSame($this->gym->id, $m->gym_id);
        $this->assertSame($this->member->id, $m->recorded_by);
        $this->assertSame(82.4, $m->weight_kg);
    }

    public function test_measurement_validation(): void
    {
        $this->actingAs($this->member)->post($this->url('/app/progreso/medidas'), [
            'measured_on' => '2026-11-19', // futuro
            'weight_kg' => 900,
            'arm_cm' => 30.25,
        ])->assertSessionHasErrors([
            'measured_on' => 'La fecha no puede ser futura.',
            'weight_kg' => 'El valor de peso debe estar entre 25 y 350 kg.',
            'arm_cm' => 'Usa máximo un decimal en brazo.',
        ]);

        $this->actingAs($this->member)->post($this->url('/app/progreso/medidas'), [
            'measured_on' => '2026-11-18',
        ])->assertSessionHasErrors(['weight_kg' => 'Captura al menos una medida.']);

        $this->measurement($this->gym, $this->member, '2026-11-18', ['weight_kg' => 80]);
        $this->actingAs($this->member)->post($this->url('/app/progreso/medidas'), [
            'measured_on' => '2026-11-18', 'weight_kg' => 79,
        ])->assertSessionHasErrors(['measured_on' => 'Ya hay una valoración registrada en esa fecha.']);

        $this->assertSame(1, BodyMeasurement::withoutGlobalScopes()->count());
    }

    public function test_member_updates_weekly_goal_and_cache_is_refreshed(): void
    {
        $this->scans($this->gym, $this->member, '2026-11-16 08:00:00', '2026-11-17 08:00:00');

        $this->actingAs($this->member)->get($this->url('/app/progreso'))
            ->assertInertia(fn (Assert $page) => $page->where('streak.goal', 3)->where('streak.goalMet', false));

        $this->actingAs($this->member)->put($this->url('/app/progreso/meta'), ['weekly_attendance_goal' => 2])
            ->assertRedirect()->assertSessionHas('success');

        $goal = MemberGoal::withoutGlobalScopes()->sole();
        $this->assertSame($this->gym->id, $goal->gym_id);
        $this->assertSame(2, $goal->weekly_attendance_goal);

        $this->actingAs($this->member)->get($this->url('/app/progreso'))
            ->assertInertia(fn (Assert $page) => $page->where('streak.goal', 2)->where('streak.goalMet', true)->where('streak.goalIsCustom', true));

        $this->actingAs($this->member)->put($this->url('/app/progreso/meta'), ['weekly_attendance_goal' => 8])
            ->assertSessionHasErrors(['weekly_attendance_goal' => 'Tu meta debe ser de 1 a 7 días por semana.']);
        $this->assertSame(1, MemberGoal::withoutGlobalScopes()->count());
    }

    public function test_reminder_shows_on_progress_page_after_30_days(): void
    {
        $this->measurement($this->gym, $this->member, '2026-10-14', ['weight_kg' => 80]);

        $this->actingAs($this->member)->get($this->url('/app/progreso'))
            ->assertInertia(fn (Assert $page) => $page->where('progress.reminder.reason', 'due')->where('progress.reminder.daysSince', 35));
    }

    public function test_staff_cannot_open_member_portal(): void
    {
        $trainer = User::factory()->forGym($this->gym)->role(Role::Trainer)->create();

        $this->actingAs($trainer)->get($this->url('/app/progreso'))->assertForbidden();
    }
}
