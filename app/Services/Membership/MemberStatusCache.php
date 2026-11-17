<?php

namespace App\Services\Membership;

use App\Models\Membership;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Cache;

/**
 * Caché (Redis) para que el escáner casi nunca toque la BD rentada:
 *
 *   gym:{id}:qr:{token}             → user_id
 *   gym:{id}:member:{user}:status   → estado de la membresía
 *   gym:{id}:member:{user}:checkin  → candado anti doble registro
 *
 * Se invalida desde los eventos del modelo Membership.
 */
class MemberStatusCache
{
    /** La vigencia se calcula al escanear, así que el dato no "caduca" por fecha. */
    public const TTL = 86400;

    public function __construct(private TenantContext $tenant) {}

    public function userIdForToken(string $token): ?int
    {
        $userId = Cache::remember(
            $this->tenant->key('qr', $token),
            self::TTL,
            fn () => Membership::query()->where('qr_token', $token)->value('user_id'),
        );

        return $userId ? (int) $userId : null;
    }

    /**
     * @return array{membership_id: int, user_id: int, name: string, expires_on: ?string, credits: int}|null
     */
    public function status(int $userId): ?array
    {
        return Cache::remember(
            $this->tenant->key('member', $userId, 'status'),
            self::TTL,
            fn () => self::snapshot(Membership::query()->with('user:id,name')->where('user_id', $userId)->first()),
        );
    }

    public function forget(int $userId): void
    {
        Cache::forget($this->tenant->key('member', $userId, 'status'));
    }

    public function lockKey(int $userId): string
    {
        return $this->tenant->key('member', $userId, 'checkin');
    }

    public static function snapshot(?Membership $membership): ?array
    {
        if (! $membership || ! $membership->user) {
            return null;
        }

        return [
            'membership_id' => (int) $membership->id,
            'user_id' => (int) $membership->user_id,
            'name' => $membership->user->name,
            'expires_on' => $membership->expires_on?->toDateString(),
            'credits' => (int) $membership->credits,
        ];
    }

    /** Llamado al guardar / borrar una membresía (también el token anterior del QR). */
    public static function forgetFor(Membership $membership): void
    {
        $gymId = (int) $membership->gym_id;

        Cache::forget(self::keyFor($gymId, 'member', $membership->user_id, 'status'));

        foreach (array_unique(array_filter([$membership->qr_token, $membership->getOriginal('qr_token')])) as $token) {
            Cache::forget(self::keyFor($gymId, 'qr', $token));
        }
    }

    /**
     * Misma llave que TenantContext::key(). Si el modelo es del gimnasio
     * activo se usa el contexto; si no (consola, bypass) se arma igual.
     */
    private static function keyFor(int $gymId, string|int ...$parts): string
    {
        $tenant = app(TenantContext::class);

        return $tenant->id() === $gymId
            ? $tenant->key(...$parts)
            : implode(':', ['gym', $gymId, ...$parts]);
    }
}
