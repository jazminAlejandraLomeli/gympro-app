<?php

namespace App\Services\Announcements;

use App\Models\Announcement;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Avisos vigentes para la tarjeta del inicio del socio.
 *
 * Se cachea en Redis por gimnasio (`gym:{id}:announcements:visible`)
 * hasta 5 minutos, pero nunca más allá del siguiente cambio de vigencia
 * (un aviso que empieza o expira), así que la vigencia se aplica sola
 * sin cron. Guardar o borrar un aviso invalida la caché (Announcement::booted).
 *
 *   'announcementsCard' => VisibleAnnouncements::forMemberHome($request->user()),
 *   <AnnouncementsCard {...announcementsCard} />
 */
class VisibleAnnouncements
{
    public const TTL = 300;

    /** Máximo de avisos que se muestran en la tarjeta. */
    public const LIMIT = 6;

    /** @return array{announcements: array<int, array<string, mixed>>} */
    public static function forMemberHome(User $user): array
    {
        $tenant = app(TenantContext::class);
        $key = $tenant->key('announcements', 'visible');

        $cached = Cache::get($key);

        if (! is_array($cached) || ($cached['valid_until'] ?? 0) <= now()->getTimestamp()) {
            $cached = self::build();
            $ttl = max(1, $cached['valid_until'] - now()->getTimestamp());
            Cache::put($key, $cached, $ttl);
        }

        return ['announcements' => $cached['items']];
    }

    /** Borra la caché del gimnasio indicado (o del activo). */
    public static function forget(?int $gymId = null): void
    {
        $tenant = app(TenantContext::class);
        $gymId ??= $tenant->id();

        if ($gymId === null) {
            return;
        }

        $key = $tenant->id() === $gymId
            ? $tenant->key('announcements', 'visible')
            : implode(':', ['gym', $gymId, 'announcements', 'visible']);

        Cache::forget($key);
    }

    /** @return array{items: array<int, array<string, mixed>>, valid_until: int} */
    private static function build(): array
    {
        $now = now();

        $items = Announcement::query()
            ->visible($now)
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Announcement $a) => [
                'id' => $a->id,
                'type' => $a->type->value,
                'typeLabel' => $a->type->label(),
                'title' => $a->title,
                'body' => $a->body,
                'pinned' => $a->pinned,
                'starts_at' => $a->starts_at->toIso8601String(),
                'expires_at' => $a->expires_at?->toIso8601String(),
            ])
            ->all();

        // Siguiente momento en que la lista puede cambiar sola
        $nextStart = Announcement::query()->where('starts_at', '>', $now)->min('starts_at');
        $nextExpiry = Announcement::query()->where('expires_at', '>', $now)->min('expires_at');

        $validUntil = $now->getTimestamp() + self::TTL;
        foreach ([$nextStart, $nextExpiry] as $boundary) {
            if ($boundary !== null) {
                $validUntil = min($validUntil, Carbon::parse($boundary)->getTimestamp());
            }
        }

        return ['items' => $items, 'valid_until' => $validUntil];
    }
}
