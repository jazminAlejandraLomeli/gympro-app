<?php

namespace App\Services\Membership;

use App\Models\Membership;
use App\Models\User;

/** Props de la membresía para las pantallas del socio. */
class MembershipSummary
{
    /**
     * Props de <MembershipCard /> en el inicio del socio:
     *
     *   'membership' => MembershipSummary::forMemberHome($request->user()),
     *
     * @return array{hasMembership: bool, status: string, statusLabel: string, planName: ?string, expiresOn: ?string, daysRemaining: ?int, credits: int, credentialUrl: string}
     */
    public static function forMemberHome(User $user): array
    {
        $membership = Membership::query()->where('user_id', $user->getKey())->first();

        return self::fromMembership($membership);
    }

    public static function fromMembership(?Membership $membership): array
    {
        $credentialUrl = route('member.credential', absolute: false);

        if (! $membership) {
            return [
                'hasMembership' => false,
                'status' => 'none',
                'statusLabel' => 'Sin membresía',
                'planName' => null,
                'expiresOn' => null,
                'daysRemaining' => null,
                'credits' => 0,
                'credentialUrl' => $credentialUrl,
            ];
        }

        return [
            'hasMembership' => true,
            'status' => $membership->status(),
            'statusLabel' => $membership->statusLabel(),
            'planName' => $membership->plan_name,
            'expiresOn' => $membership->expires_on?->toDateString(),
            'daysRemaining' => $membership->daysRemaining(),
            'credits' => (int) $membership->credits,
            'credentialUrl' => $credentialUrl,
        ];
    }
}
