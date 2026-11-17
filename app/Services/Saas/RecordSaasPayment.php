<?php

namespace App\Services\Saas;

use App\Enums\GymStatus;
use App\Models\Gym;
use App\Models\SaasPayment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Registra un pago manual de la licencia SaaS.
 *
 *   saas_paid_until = max(hoy, saas_paid_until actual) + meses
 *
 * Si el gimnasio estaba suspendido, lo reactiva.
 */
class RecordSaasPayment
{
    /**
     * @param  array{amount: numeric, months: int, paid_at?: string|null, method: string, notes?: string|null}  $data
     */
    public function handle(Gym $gym, array $data, ?User $recordedBy = null): SaasPayment
    {
        return DB::transaction(function () use ($gym, $data, $recordedBy) {
            // Bloquea la fila para que dos pagos simultáneos no se pisen
            $gym = Gym::query()->lockForUpdate()->findOrFail($gym->getKey());

            $coversUntil = self::extend($gym->saas_paid_until, (int) $data['months']);

            $payment = new SaasPayment([
                'amount' => $data['amount'],
                'months' => (int) $data['months'],
                'paid_at' => $data['paid_at'] ?? today(),
                'covers_until' => $coversUntil,
                'method' => $data['method'],
                'notes' => $data['notes'] ?? null,
            ]);
            $payment->gym_id = $gym->id;
            $payment->recorded_by = $recordedBy?->id;
            $payment->save();

            $gym->saas_paid_until = $coversUntil;

            if ($gym->status === GymStatus::Suspended) {
                $gym->status = GymStatus::Active;
                $gym->suspended_at = null;
            }

            $gym->save(); // invalida el caché del subdominio (Gym::booted)

            return $payment;
        });
    }

    /** max(hoy, pagado hasta) + meses, sin desbordar fin de mes (31 ene + 1 = 28/29 feb). */
    public static function extend(?Carbon $paidUntil, int $months): Carbon
    {
        $today = today();
        $base = $paidUntil && $paidUntil->greaterThan($today) ? $paidUntil->copy() : $today;

        return $base->addMonthsNoOverflow($months);
    }
}
