<?php

namespace Tests\Feature\Progress;

use App\Enums\Role;
use App\Models\Gym;
use App\Models\User;
use App\Services\Progress\MeasurementService;
use App\Services\Progress\StreakService;
use Database\Seeders\Demo\ProgressDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgressDemoSeederTest extends TestCase
{
    use ProgressTestHelpers, RefreshDatabase;

    public function test_demo_member_has_four_week_streak_and_pending_measurement(): void
    {
        $this->travelTo('2026-11-18 10:00:00'); // miércoles
        $gym = Gym::factory()->create(['slug' => 'irondemo']);

        $demo = $this->tenant()->runAs($gym, function () use ($gym) {
            User::factory()->role(Role::Trainer)->create();
            $demo = User::factory()->create(['email' => 'socio@demo.test']);
            User::factory()->count(6)->create();
            (new ProgressDemoSeeder)->seedGym($gym);

            return $demo;
        });

        $this->tenant()->set($gym);
        $streak = app(StreakService::class)->compute($demo);
        $this->assertSame(4, $streak['streakWeeks']);
        $this->assertSame(2, $streak['attendedThisWeek']);

        $report = app(MeasurementService::class)->report($demo);
        $this->assertCount(6, $report['history']);
        $this->assertSame('due', $report['reminder']['reason']);
        $this->assertSame(35, $report['reminder']['daysSince']);
        $this->assertStringStartsWith('¡Has bajado', collect($report['achievements'])->firstWhere('metric', 'weight_kg')['text']);
    }
}
