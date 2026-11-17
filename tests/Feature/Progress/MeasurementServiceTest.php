<?php

namespace Tests\Feature\Progress;

use App\Models\Gym;
use App\Models\User;
use App\Services\Progress\MeasurementService;
use App\Services\Progress\ProgressSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeasurementServiceTest extends TestCase
{
    use ProgressTestHelpers, RefreshDatabase;

    private Gym $gym;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gym = Gym::factory()->create(['slug' => 'irondemo']);
        $this->member = $this->memberOf($this->gym);
        $this->tenant()->set($this->gym);
        $this->travelTo('2026-11-18 10:00:00');
    }

    private function service(): MeasurementService
    {
        return app(MeasurementService::class);
    }

    public function test_reminder_when_there_are_no_measurements(): void
    {
        $reminder = $this->service()->reminder($this->member);

        $this->assertSame('never', $reminder['reason']);
    }

    public function test_reminder_appears_at_30_days(): void
    {
        $this->measurement($this->gym, $this->member, '2026-10-20', ['weight_kg' => 80]); // 29 días
        $this->assertNull($this->service()->reminder($this->member));

        $this->travelTo('2026-11-19 09:00:00'); // 30 días
        $reminder = $this->service()->reminder($this->member);
        $this->assertSame('due', $reminder['reason']);
        $this->assertSame(30, $reminder['daysSince']);
        $this->assertStringContainsString('30 días', $reminder['message']);
    }

    public function test_achievement_wording_depends_on_direction_and_metric(): void
    {
        $this->measurement($this->gym, $this->member, '2026-05-01', [
            'weight_kg' => 92, 'waist_cm' => 100, 'arm_cm' => 33, 'chest_cm' => 105, 'body_fat_pct' => 30, 'leg_cm' => 60,
        ]);
        $this->measurement($this->gym, $this->member, '2026-08-01', ['weight_kg' => 85]);
        $this->measurement($this->gym, $this->member, '2026-11-01', [
            'weight_kg' => 80, 'waist_cm' => 94, 'arm_cm' => 35, 'chest_cm' => 103.5, 'body_fat_pct' => 31.5, 'leg_cm' => 60,
        ]);

        $report = $this->service()->report($this->member);
        $cards = collect($report['achievements'])->keyBy('metric');

        $this->assertSame('2026-05-01', $report['baseline']['measured_on']);
        $this->assertSame('¡Has bajado 12 kg desde tu inicio!', $cards['weight_kg']['text']);
        $this->assertSame('-12 kg', $cards['weight_kg']['deltaLabel']);
        $this->assertTrue($cards['weight_kg']['positive']);

        $this->assertSame('-6 cm', $cards['waist_cm']['deltaLabel']);
        $this->assertTrue($cards['waist_cm']['positive']);

        $this->assertSame('+2 cm', $cards['arm_cm']['deltaLabel']);
        $this->assertSame('¡Ganaste 2 cm de brazo!', $cards['arm_cm']['text']);
        $this->assertTrue($cards['arm_cm']['positive']);

        // Bajar pecho o subir grasa no es "logro": redacción neutral
        $this->assertSame('Tu pecho se redujo 1.5 cm.', $cards['chest_cm']['text']);
        $this->assertFalse($cards['chest_cm']['positive']);
        $this->assertSame('Tu grasa corporal subió 1.5 puntos.', $cards['body_fat_pct']['text']);
        $this->assertFalse($cards['body_fat_pct']['positive']);

        // Sin cambio → sin tarjeta; logros primero
        $this->assertFalse($cards->has('leg_cm'));
        $this->assertTrue($report['achievements'][0]['positive']);
        $this->assertFalse(end($report['achievements'])['positive']);

        $this->assertCount(3, $report['chart']);
        $this->assertSame('2026-11-01', $report['history'][0]['measured_on']);
    }

    public function test_weight_gain_is_worded_neutrally(): void
    {
        $this->measurement($this->gym, $this->member, '2026-09-01', ['weight_kg' => 60]);
        $this->measurement($this->gym, $this->member, '2026-11-01', ['weight_kg' => 63.5]);

        $card = $this->service()->report($this->member)['achievements'][0];

        $this->assertSame('Has subido 3.5 kg desde tu inicio.', $card['text']);
        $this->assertSame('+3.5 kg', $card['deltaLabel']);
        $this->assertFalse($card['positive']);
    }

    public function test_single_measurement_has_no_achievements(): void
    {
        $this->measurement($this->gym, $this->member, '2026-11-10', ['weight_kg' => 70]);

        $this->assertSame([], $this->service()->report($this->member)['achievements']);
    }

    public function test_member_home_summary(): void
    {
        $this->measurement($this->gym, $this->member, '2026-10-01', ['weight_kg' => 70]);

        $summary = ProgressSummary::forMemberHome($this->member);

        $this->assertSame(['streak', 'measurementReminder'], array_keys($summary));
        $this->assertSame('2026-11-16', $summary['streak']['weekStart']);
        $this->assertSame('due', $summary['measurementReminder']['reason']);
        $this->assertSame('/app/progreso#registrar', $summary['measurementReminder']['href']);

        $this->measurement($this->gym, $this->member, '2026-11-17', ['weight_kg' => 69]);
        $this->assertNull(ProgressSummary::forMemberHome($this->member)['measurementReminder']);
    }
}
