<?php

namespace Tests\Feature\Admin;

use App\Enums\GymStatus;
use App\Models\Gym;
use App\Models\SaasPayment;
use App\Services\Saas\SaasMetrics;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

class SaasPaymentTest extends AdminTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function pay(Gym $gym, array $data = [])
    {
        return $this->actingAs($this->super)->post($this->url("/admin/gimnasios/{$gym->id}/pagos"), array_merge([
            'months' => 1,
            'amount' => 1800,
            'method' => 'transferencia',
        ], $data));
    }

    public function test_payment_extends_from_current_paid_until_when_in_the_future(): void
    {
        Carbon::setTestNow('2026-11-16 12:00:00');
        $gym = Gym::factory()->create(['saas_paid_until' => '2026-12-10']);

        $this->pay($gym, ['months' => 3, 'amount' => 5400])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame('2027-03-10', $gym->fresh()->saas_paid_until->toDateString());

        $payment = SaasPayment::query()->sole();
        $this->assertSame($gym->id, $payment->gym_id);
        $this->assertSame($this->super->id, $payment->recorded_by);
        $this->assertSame('2027-03-10', $payment->covers_until->toDateString());
        $this->assertSame('2026-11-16', $payment->paid_at->toDateString());
    }

    public function test_payment_extends_from_today_when_overdue_and_reactivates(): void
    {
        Carbon::setTestNow('2026-11-16 12:00:00');
        $gym = Gym::factory()->suspended()->create(['slug' => 'pulso', 'saas_paid_until' => '2026-09-01']);

        $this->pay($gym, ['months' => 2])->assertSessionHasNoErrors();

        $gym->refresh();
        $this->assertSame('2027-01-16', $gym->saas_paid_until->toDateString());
        $this->assertSame(GymStatus::Active, $gym->status);
        $this->assertNull($gym->suspended_at);

        // El subdominio vuelve a dar servicio
        $this->app['auth']->forgetGuards();
        $this->get($this->url('/login', 'pulso'))->assertOk();
    }

    public function test_first_payment_without_paid_until_starts_today(): void
    {
        Carbon::setTestNow('2026-01-31 09:00:00');
        $gym = Gym::factory()->create(['saas_paid_until' => null]);

        $this->pay($gym);

        // Sin desbordar: 31 ene + 1 mes = 28 feb
        $this->assertSame('2026-02-28', $gym->fresh()->saas_paid_until->toDateString());
    }

    public function test_payment_validation(): void
    {
        $gym = Gym::factory()->create();

        $this->pay($gym, ['months' => 0, 'amount' => -5, 'method' => 'bitcoin', 'paid_at' => today()->addDay()->toDateString()])
            ->assertSessionHasErrors(['months', 'amount', 'method', 'paid_at']);

        $this->assertSame(0, SaasPayment::query()->count());
    }

    public function test_mrr_counts_net_fee_of_active_gyms_only(): void
    {
        Gym::factory()->create(['saas_monthly_fee' => 1800, 'saas_discount' => 0]);
        Gym::factory()->create(['saas_monthly_fee' => 1800, 'saas_discount' => 300]);
        // Descuento mayor que la cuota: cuenta 0, nunca negativo
        Gym::factory()->create(['saas_monthly_fee' => 500, 'saas_discount' => 800]);
        // Suspendido: no cuenta
        Gym::factory()->suspended()->create(['saas_monthly_fee' => 1800, 'saas_discount' => 0]);

        $this->assertSame(3300.0, app(SaasMetrics::class)->mrr());

        $this->actingAs($this->super)->get($this->url('/admin/finanzas'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Finance')
                ->where('mrr', 3300)
                ->has('revenue', 12)
                ->where('projection.0.months', 3)
                ->where('projection.0.projected', 9900));
    }

    public function test_finance_lists_expiring_overdue_and_monthly_revenue(): void
    {
        Carbon::setTestNow('2026-11-16 12:00:00');
        $soon = Gym::factory()->create(['name' => 'Pronto', 'saas_paid_until' => '2026-11-23']);
        Gym::factory()->create(['name' => 'Vencido', 'saas_paid_until' => '2026-11-15']);
        Gym::factory()->create(['name' => 'Al día', 'saas_paid_until' => '2026-11-24']);

        $payment = new SaasPayment(['amount' => 1800, 'months' => 1, 'paid_at' => '2026-10-03', 'covers_until' => '2026-11-23', 'method' => 'efectivo']);
        $payment->gym_id = $soon->id;
        $payment->save();

        $this->actingAs($this->super)->get($this->url('/admin/finanzas'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('expiring.0.name', 'Pronto')
                ->has('expiring', 1)
                ->where('overdue.0.name', 'Vencido')
                ->has('overdue', 1)
                ->where('revenue.10.month', '2026-10')
                ->where('revenue.10.total', 1800)
                ->where('revenue.11.month', '2026-11')
                ->has('payments.data', 1));
    }
}
