<?php

namespace Database\Seeders\Demo;

use App\Actions\Gyms\CreateGym;
use App\Enums\GymStatus;
use App\Enums\Role;
use App\Models\Gym;
use App\Models\SaasPayment;
use App\Models\User;
use App\Tenancy\TenantScope;
use Illuminate\Support\Carbon;

/**
 * Módulo 0 — datos centrales del SaaS (solo desarrollo):
 *  - Iron Demo: cliente antiguo, pagos mensuales al corriente.
 *  - Titan Fit: referido por Iron (con descuento), vence en pocos días.
 *  - Pulso Box: suspendido por falta de pago.
 */
class SaasDemoSeeder
{
    public function __construct(private CreateGym $createGym) {}

    public function seedCentral(): void
    {
        $recorder = User::withoutGlobalScope(TenantScope::class)
            ->where('role', Role::SuperAdmin)->value('id');

        if ($iron = Gym::query()->where('slug', 'irondemo')->first()) {
            // 11 meses pagados mes a mes: al corriente ~1 mes más
            $this->history($iron, today()->subMonths(10)->startOfMonth()->addDays(4), 11, 1, $recorder);
        }

        if ($titan = Gym::query()->where('slug', 'titan')->first()) {
            $titan->saas_discount = 300; // descuento por venir referido
            $titan->save();
            // Pagó 3 meses por adelantado, luego mes a mes; vence en 5 días
            $this->history($titan, today()->addDays(5)->subMonthsNoOverflow(6), 4, [3, 1, 1, 1], $recorder);
        }

        if (! Gym::query()->where('slug', 'pulso')->exists()) {
            $pulso = $this->createGym->handle(
                ['name' => 'Pulso Box', 'slug' => 'pulso', 'primary_color' => '#0ea5e9', 'secondary_color' => '#0c4a6e'],
                ['name' => 'Admin Pulso', 'email' => 'admin@pulso.test', 'password' => 'password'],
            );
            // Pagó 2 meses y dejó de pagar hace 3 semanas
            $this->history($pulso, today()->subDays(21)->subMonthsNoOverflow(2), 2, 1, $recorder);

            $pulso->refresh();
            $pulso->status = GymStatus::Suspended;
            $pulso->suspended_at = today()->subDays(14)->setTime(10, 0);
            $pulso->save();
        }
    }

    /**
     * Crea pagos consecutivos que empiezan en $start y deja
     * saas_paid_until en la cobertura del último.
     *
     * @param  int|list<int>  $months  meses por pago (uno para todos o uno por pago)
     */
    private function history(Gym $gym, Carbon $start, int $count, int|array $months, ?int $recorder): void
    {
        if (SaasPayment::query()->where('gym_id', $gym->id)->exists()) {
            return; // idempotente
        }

        $net = max(0, (float) $gym->saas_monthly_fee - (float) $gym->saas_discount);
        $cursor = $start->copy();
        $methods = array_keys(SaasPayment::METHODS);

        for ($i = 0; $i < $count; $i++) {
            $m = is_array($months) ? $months[$i] : $months;
            $covers = $cursor->copy()->addMonthsNoOverflow($m);

            $payment = new SaasPayment([
                'amount' => $net * $m,
                'months' => $m,
                // Pagan unos días antes o después del corte
                'paid_at' => $cursor->copy()->subDays(($i * 3) % 5)->min(today()),
                'covers_until' => $covers,
                'method' => $methods[$i % 3],
                'notes' => $i === 0 ? 'Primer pago de licencia' : null,
            ]);
            $payment->gym_id = $gym->id;
            $payment->recorded_by = $recorder;
            $payment->save();

            $cursor = $covers;
        }

        $gym->saas_paid_until = $cursor;
        $gym->save();
    }
}
