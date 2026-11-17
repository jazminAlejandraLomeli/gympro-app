<?php

namespace App\Models;

use App\Services\Membership\MemberStatusCache;
use App\Tenancy\BelongsToGym;
use App\Tenancy\TenantScope;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

/**
 * Membresía de un socio en su gimnasio (Módulo A).
 *
 * El socio está ACTIVO si hoy <= expires_on O credits > 0.
 * qr_token es el contenido (firmado) de su credencial QR.
 *
 * user_id y qr_token no son fillable: se asignan en el código.
 */
#[Fillable(['plan_name', 'phone', 'expires_on', 'credits'])]
class Membership extends Model
{
    use BelongsToGym, Searchable;

    /** Días antes del vencimiento en que se considera "por vencer". */
    public const EXPIRING_DAYS = 7;

    public const STATUSES = [
        'active' => 'Activa',
        'expiring' => 'Por vencer',
        'expired' => 'Vencida',
    ];

    protected $attributes = [
        'credits' => 0,
    ];

    protected function casts(): array
    {
        return [
            'expires_on' => 'date',
            'credits' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Membership $membership) {
            $membership->qr_token ??= (string) Str::uuid();
        });

        // Validación del escáner: invalidar el estado y el token en Redis
        static::saved(fn (Membership $m) => MemberStatusCache::forgetFor($m));
        static::deleted(fn (Membership $m) => MemberStatusCache::forgetFor($m));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(MemberPayment::class, 'user_id', 'user_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'user_id', 'user_id');
    }

    /** Nuevo QR (el anterior deja de funcionar al guardar). */
    public function regenerateQrToken(): void
    {
        $this->qr_token = (string) Str::uuid();
    }

    // ── Estado ───────────────────────────────────────────────────────

    public function activeByDate(?CarbonInterface $today = null): bool
    {
        $today ??= today();

        return $this->expires_on !== null && $this->expires_on->gte($today->copy()->startOfDay());
    }

    public function isActive(?CarbonInterface $today = null): bool
    {
        return $this->activeByDate($today) || $this->credits > 0;
    }

    /** active | expiring | expired */
    public function status(?CarbonInterface $today = null): string
    {
        $today ??= today();

        if ($this->activeByDate($today) && $this->expires_on->lte($today->copy()->addDays(self::EXPIRING_DAYS))) {
            return 'expiring';
        }

        return $this->isActive($today) ? 'active' : 'expired';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status()];
    }

    /** Días que le quedan por fecha (null si no tiene vigencia por fecha). */
    public function daysRemaining(?CarbonInterface $today = null): ?int
    {
        $today ??= today();

        return $this->activeByDate($today)
            ? (int) $today->copy()->startOfDay()->diffInDays($this->expires_on->copy()->startOfDay())
            : null;
    }

    /** Filtro por estado en SQL (mismo criterio que status()). */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        $today = today()->toDateString();
        $expires = $this->qualifyColumn('expires_on');
        $credits = $this->qualifyColumn('credits');

        return match ($status) {
            'active' => $query->where(fn ($q) => $q->where($expires, '>=', $today)->orWhere($credits, '>', 0)),
            'expiring' => $query->whereBetween($expires, [$today, today()->addDays(self::EXPIRING_DAYS)->toDateString()]),
            'expired' => $query->where($credits, '<=', 0)
                ->where(fn ($q) => $q->whereNull($expires)->orWhere($expires, '<', $today)),
            default => $query,
        };
    }

    // ── Búsqueda (Scout / Meilisearch) ───────────────────────────────

    /**
     * Siempre se busca con ->where('gym_id', $tenantId): el índice es
     * compartido por todos los gimnasios (ver MemberSearch).
     */
    public function toSearchableArray(): array
    {
        $user = $this->getRelationValue('user')
            ?? $this->user()->withoutGlobalScope(TenantScope::class)->first();

        return [
            'id' => (int) $this->getKey(),
            'gym_id' => (int) $this->gym_id,
            'name' => (string) $user?->name,
            'email' => (string) $user?->email,
            'phone' => (string) $this->phone,
        ];
    }

    /**
     * El worker de la cola y scout:import no tienen gimnasio activo:
     * se carga el usuario por su id, sin el filtro de tenant.
     */
    public function makeSearchableUsing(Collection $models): Collection
    {
        if ($models instanceof EloquentCollection) {
            $models->loadMissing(['user' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)]);
        }

        return $models;
    }

    protected function makeAllSearchableUsing(Builder $query): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class)
            ->with(['user' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)]);
    }

    /**
     * Restaurar un modelo serializado en la cola (p. ej. el job de
     * indexado de Scout) por su id exacto, aunque el worker no tenga
     * gimnasio activo. Los ids vienen de la serialización, no del usuario.
     */
    public function newQueryForRestoration($ids)
    {
        return parent::newQueryForRestoration($ids)->withoutGlobalScope(TenantScope::class);
    }
}
