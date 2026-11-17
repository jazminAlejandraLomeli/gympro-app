<?php

namespace App\Models;

use App\Enums\GymStatus;
use App\Tenancy\TenantScope;
use Database\Factories\GymFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;

/**
 * Tenant. Este modelo NO usa BelongsToGym: es la raíz del aislamiento
 * y solo el super_admin (o el resolvedor de subdominio) lo consulta.
 */
#[Fillable(['name', 'slug', 'saas_monthly_fee', 'saas_discount', 'saas_paid_until', 'referred_by_gym_id'])]
class Gym extends Model
{
    /** @use HasFactory<GymFactory> */
    use HasFactory;

    /** Segundos que se guarda en Redis la búsqueda por subdominio. */
    public const SLUG_CACHE_TTL = 600;

    /** Igual al default de la BD, para que un modelo recién creado lo tenga. */
    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'status' => GymStatus::class,
            'saas_monthly_fee' => 'decimal:2',
            'saas_discount' => 'decimal:2',
            'saas_paid_until' => 'date',
            'suspended_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        $forget = function (Gym $gym) {
            Cache::forget(self::slugCacheKey($gym->slug));
            if ($gym->getOriginal('slug') && $gym->getOriginal('slug') !== $gym->slug) {
                Cache::forget(self::slugCacheKey($gym->getOriginal('slug')));
            }
        };

        static::saved($forget);
        static::deleted($forget);
    }

    public static function slugCacheKey(string $slug): string
    {
        return "tenant:slug:{$slug}";
    }

    /**
     * Busca por subdominio pasando primero por Redis, para no ir a la
     * BD rentada (remota) en cada request.
     */
    public static function findCachedBySlug(string $slug): ?self
    {
        $attributes = Cache::remember(
            self::slugCacheKey($slug),
            self::SLUG_CACHE_TTL,
            fn () => static::query()->where('slug', $slug)->first()?->getAttributes(),
        );

        return $attributes ? (new static)->newFromBuilder($attributes) : null;
    }

    public function isActive(): bool
    {
        return $this->status === GymStatus::Active;
    }

    /*
     * Las relaciones desde Gym ya filtran por su propio gym_id, así que
     * se quita el TenantScope (si no, sin gimnasio activo darían vacío).
     * Quien tiene un Gym en mano es el resolvedor o el super_admin.
     */

    public function settings(): HasOne
    {
        return $this->hasOne(GymSettings::class)->withoutGlobalScope(TenantScope::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class)->withoutGlobalScope(TenantScope::class);
    }
}
