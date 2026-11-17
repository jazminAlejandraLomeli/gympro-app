<?php

namespace App\Services\Saas;

use App\Enums\GymStatus;
use App\Models\Gym;
use App\Models\SaasPayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/** Cifras de negocio del SaaS: MRR, ingresos, vencimientos y proyección. */
class SaasMetrics
{
    /** Días antes del vencimiento en que un gimnasio cuenta como "por vencer". */
    public const EXPIRING_DAYS = 7;

    /** Cuota neta mensual: max(0, cuota - descuento). Funciona en MySQL y SQLite. */
    public const NET_FEE_SQL = 'CASE WHEN saas_monthly_fee - saas_discount > 0 THEN saas_monthly_fee - saas_discount ELSE 0 END';

    /** MRR = suma de la cuota neta de los gimnasios activos. */
    public function mrr(): float
    {
        return round((float) Gym::query()
            ->where('status', GymStatus::Active)
            ->sum(DB::raw(self::NET_FEE_SQL)), 2);
    }

    public static function netFee(Gym $gym): float
    {
        return max(0, (float) $gym->saas_monthly_fee - (float) $gym->saas_discount);
    }

    /**
     * Estado de cobro de un gimnasio.
     *   none     → nunca ha pagado
     *   overdue  → vencido
     *   expiring → vence en los próximos 7 días
     *   ok       → al corriente
     */
    public static function billingState(Gym $gym): string
    {
        $until = $gym->saas_paid_until;

        return match (true) {
            $until === null => 'none',
            $until->lt(today()) => 'overdue',
            $until->lte(today()->addDays(self::EXPIRING_DAYS)) => 'expiring',
            default => 'ok',
        };
    }

    /** Filtro de listado por vencimiento: vencidos | por_vencer | al_dia. */
    public static function applyBillingFilter(Builder $query, ?string $filter): Builder
    {
        // Rangos medio abiertos con fechas sin hora: igual en MySQL (DATE) y SQLite (texto)
        $today = today()->toDateString();
        $afterLimit = today()->addDays(self::EXPIRING_DAYS + 1)->toDateString();

        return match ($filter) {
            'vencidos' => $query->where(fn ($q) => $q->whereNull('saas_paid_until')->orWhere('saas_paid_until', '<', $today)),
            'por_vencer' => $query->where('saas_paid_until', '>=', $today)->where('saas_paid_until', '<', $afterLimit),
            'al_dia' => $query->where('saas_paid_until', '>=', $afterLimit),
            default => $query,
        };
    }

    /** Gimnasios activos que vencen en los próximos 7 días. */
    public function expiringSoon(): Collection
    {
        return self::applyBillingFilter(Gym::query()->where('status', GymStatus::Active), 'por_vencer')
            ->orderBy('saas_paid_until')
            ->get(['id', 'name', 'slug', 'saas_paid_until', 'saas_monthly_fee', 'saas_discount']);
    }

    /** Gimnasios activos vencidos o sin ningún pago (candidatos a suspender). */
    public function overdue(): Collection
    {
        return self::applyBillingFilter(Gym::query()->where('status', GymStatus::Active), 'vencidos')
            ->orderBy('saas_paid_until')
            ->get(['id', 'name', 'slug', 'saas_paid_until', 'saas_monthly_fee', 'saas_discount']);
    }

    /**
     * Ingresos cobrados por mes (por fecha de pago), últimos N meses,
     * incluyendo meses en cero.
     *
     * @return list<array{month: string, total: float, count: int}>
     */
    public function monthlyRevenue(int $months = 12): array
    {
        $start = today()->startOfMonth()->subMonths($months - 1);

        $buckets = [];
        for ($i = 0; $i < $months; $i++) {
            $buckets[$start->copy()->addMonths($i)->format('Y-m')] = ['total' => 0.0, 'count' => 0];
        }

        // Agrupa en PHP para no depender de funciones de fecha de cada motor
        SaasPayment::query()
            ->where('paid_at', '>=', $start->toDateString())
            ->select(['paid_at', 'amount'])
            ->orderBy('paid_at')
            ->lazy(500)
            ->each(function (SaasPayment $p) use (&$buckets) {
                $key = $p->paid_at->format('Y-m');
                if (isset($buckets[$key])) {
                    $buckets[$key]['total'] += (float) $p->amount;
                    $buckets[$key]['count']++;
                }
            });

        return collect($buckets)
            ->map(fn ($b, $month) => ['month' => $month, 'total' => round($b['total'], 2), 'count' => $b['count']])
            ->values()
            ->all();
    }

    /**
     * Proyección simple: si la red se queda como hoy, MRR × meses.
     * También indica cuánto de eso ya está pagado por adelantado.
     *
     * @return list<array{months: int, projected: float, prepaid: float}>
     */
    public function projection(?float $mrr = null): array
    {
        $mrr ??= $this->mrr();

        $active = Gym::query()
            ->where('status', GymStatus::Active)
            ->get(['id', 'saas_monthly_fee', 'saas_discount', 'saas_paid_until']);

        return collect([3, 6, 12])->map(function (int $months) use ($mrr, $active) {
            $horizon = today()->addMonthsNoOverflow($months);

            $prepaid = $active->sum(function (Gym $gym) use ($horizon) {
                $until = $gym->saas_paid_until;
                if ($until === null || $until->lte(today())) {
                    return 0;
                }
                $covered = (int) floor(today()->diffInMonths(min($until, $horizon)));

                return self::netFee($gym) * $covered;
            });

            return [
                'months' => $months,
                'projected' => round($mrr * $months, 2),
                'prepaid' => round($prepaid, 2),
            ];
        })->all();
    }

    /** Pagos recibidos este mes (dinero cobrado, no MRR). */
    public function collectedThisMonth(): float
    {
        return round((float) SaasPayment::query()
            ->where('paid_at', '>=', today()->startOfMonth()->toDateString())
            ->sum('amount'), 2);
    }
}
