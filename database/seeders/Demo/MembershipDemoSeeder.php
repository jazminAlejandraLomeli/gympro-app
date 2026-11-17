<?php

namespace Database\Seeders\Demo;

use App\Enums\Role;
use App\Models\Gym;
use App\Models\MemberPayment;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Módulo A: membresías variadas y su historial de pagos.
 * Corre dentro del gimnasio (DatabaseSeeder → TenantContext::runAs).
 *
 *   socio@demo.test → activo con ~20 días
 *   los demás       → por vencer, vencido, por créditos, activo largo…
 */
class MembershipDemoSeeder
{
    private const PLANS = [
        // [plan, días de vigencia relativos a hoy (null = sin fecha), créditos, meses pagados, precio]
        ['Mensual', 20, 0, 1, 550],          // socio@demo.test
        ['Mensual', 3, 0, 1, 550],           // por vencer
        ['Mensual', -12, 0, 1, 550],         // vencido
        ['Paquete 10 clases', -40, 6, 0, 600], // por créditos (fecha vencida)
        ['Trimestral', 65, 0, 3, 1450],      // activo largo
        ['Visitas', null, 0, 0, 0],          // sin membresía vigente
    ];

    public function seedGym(Gym $gym): void
    {
        $admin = User::query()->where('role', Role::GymAdmin)->orderBy('id')->first();

        $members = User::query()
            ->where('role', Role::Member)
            ->orderByRaw("CASE WHEN email = 'socio@demo.test' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get();

        foreach ($members as $i => $user) {
            [$plan, $days, $credits, $months, $price] = self::PLANS[$i % count(self::PLANS)];

            $membership = Membership::query()->firstOrNew(['user_id' => $user->id]);
            $membership->user_id = $user->id;
            $membership->plan_name = $plan;
            $membership->phone = '55'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
            $membership->expires_on = $days === null ? null : today()->addDays($days);
            $membership->credits = $credits;
            $membership->save();

            $this->seedPayments($user, $membership, $admin, $months, $credits, $price);
        }
    }

    /** Historial: el pago que dejó la vigencia actual y algunos anteriores. */
    private function seedPayments(User $user, Membership $membership, ?User $admin, int $months, int $credits, int $price): void
    {
        if ($price <= 0) {
            return;
        }

        $methods = array_keys(MemberPayment::METHODS);
        $lastPaidAt = $membership->expires_on
            ? $membership->expires_on->copy()->subMonthsNoOverflow(max($months, 1))->setTime(9, 30)
            : now()->subDays(20);

        // Último pago + dos meses anteriores
        foreach (range(0, 2) as $k) {
            $paidAt = Carbon::parse($lastPaidAt)->subMonthsNoOverflow($k * max($months, 1))->addMinutes(random_int(0, 600));

            if ($paidAt->isFuture()) {
                $paidAt = now()->subHour();
            }

            $payment = new MemberPayment([
                'amount' => $price,
                'concept' => $months > 0
                    ? "Membresía {$membership->plan_name}: ".($months === 1 ? '1 mes' : "{$months} meses")
                    : "Paquete: {$credits} créditos",
                'method' => $methods[($user->id + $k) % count($methods)],
                'months_added' => $months,
                'credits_added' => $months > 0 ? 0 : 10,
                'paid_at' => $paidAt,
            ]);
            $payment->user_id = $user->id;
            $payment->recorded_by = $admin?->id;
            $payment->save();
        }
    }
}
