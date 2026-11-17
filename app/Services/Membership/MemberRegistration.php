<?php

namespace App\Services\Membership;

use App\Enums\Role;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Alta de socio: usuario (role member) + membresía + pago inicial opcional. */
class MemberRegistration
{
    public function __construct(private MembershipBilling $billing) {}

    /**
     * @return array{0: Membership, 1: string} membresía y contraseña inicial (se muestra una sola vez)
     */
    public function register(array $data, User $registeredBy): array
    {
        return DB::transaction(function () use ($data, $registeredBy) {
            $password = self::generatePassword();

            $user = new User([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'password' => $password,
            ]);
            $user->role = Role::Member; // gym_id lo pone BelongsToGym
            $user->save();

            $membership = new Membership([
                'plan_name' => $data['plan_name'] ?? null,
                'phone' => $data['phone'] ?? null,
            ]);
            $membership->user_id = $user->id;
            $membership->save();

            $months = (int) ($data['months_added'] ?? 0);
            $credits = (int) ($data['credits_added'] ?? 0);

            if ($months > 0 || $credits > 0) {
                $this->billing->recordPayment($membership, [
                    'amount' => $data['amount'] ?? 0,
                    'concept' => 'Inscripción · '.MembershipBilling::defaultConcept($months, $credits),
                    'method' => $data['method'] ?? 'efectivo',
                    'months_added' => $months,
                    'credits_added' => $credits,
                ], $registeredBy);
            }

            return [$membership, $password];
        });
    }

    /** Fácil de dictar: sin símbolos ni caracteres parecidos (0/O, 1/l). */
    public static function generatePassword(int $length = 10): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out = '';

        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $out;
    }
}
