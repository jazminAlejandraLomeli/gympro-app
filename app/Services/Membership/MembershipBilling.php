<?php

namespace App\Services\Membership;

use App\Models\MemberPayment;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cobro pasivo: recepción registra el pago y eso renueva la membresía.
 *
 *   expires_on = max(hoy, expires_on) + meses
 *   credits   += créditos
 */
class MembershipBilling
{
    public function __construct(private MemberStatusCache $cache) {}

    /**
     * @param  array{amount: numeric, concept?: ?string, method: string, months_added?: ?int, credits_added?: ?int}  $data
     */
    public function recordPayment(Membership $membership, array $data, User $recordedBy): MemberPayment
    {
        $months = (int) ($data['months_added'] ?? 0);
        $credits = (int) ($data['credits_added'] ?? 0);

        $payment = DB::transaction(function () use ($membership, $data, $recordedBy, $months, $credits) {
            // Bloquea la fila: dos cobros simultáneos no se pisan
            $locked = Membership::query()->whereKey($membership->getKey())->lockForUpdate()->firstOrFail();

            if ($months > 0) {
                $base = $locked->activeByDate() ? $locked->expires_on->copy() : today();
                $locked->expires_on = $base->addMonthsNoOverflow($months);
            }

            if ($credits > 0) {
                $locked->credits += $credits;
            }

            $payment = new MemberPayment([
                'amount' => $data['amount'],
                'concept' => ($data['concept'] ?? null) ?: self::defaultConcept($months, $credits),
                'method' => $data['method'],
                'months_added' => $months,
                'credits_added' => $credits,
                'paid_at' => now(),
            ]);
            $payment->user_id = $locked->user_id;
            $payment->recorded_by = $recordedBy->getKey();
            $payment->save();

            $locked->save(); // eventos del modelo → invalida Redis

            $membership->setRawAttributes($locked->getAttributes(), true);

            return $payment;
        });

        // Otra vez después del commit: un escaneo concurrente pudo cachear el dato viejo
        $this->cache->forget((int) $membership->user_id);

        return $payment;
    }

    public static function defaultConcept(int $months, int $credits): string
    {
        $parts = [];

        if ($months > 0) {
            $parts[] = $months === 1 ? '1 mes' : "{$months} meses";
        }
        if ($credits > 0) {
            $parts[] = $credits === 1 ? '1 crédito' : "{$credits} créditos";
        }

        return 'Membresía: '.($parts ? implode(' + ', $parts) : 'pago');
    }
}
