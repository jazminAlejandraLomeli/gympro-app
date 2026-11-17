<?php

namespace App\Models;

use App\Enums\AnnouncementType;
use App\Services\Announcements\VisibleAnnouncements;
use App\Tenancy\BelongsToGym;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aviso del tablón (Módulo E). La vigencia se calcula con las fechas:
 * no hay cron que "publique" o "expire" avisos.
 *
 * created_by y gym_id no son fillable: se asignan en el controlador.
 */
#[Fillable(['type', 'title', 'body', 'starts_at', 'expires_at', 'pinned'])]
class Announcement extends Model
{
    use BelongsToGym;

    public const STATUS_SCHEDULED = 'programado';

    public const STATUS_ACTIVE = 'vigente';

    public const STATUS_EXPIRED = 'expirado';

    protected $attributes = [
        'type' => 'operativo',
        'pinned' => false,
    ];

    protected function casts(): array
    {
        return [
            'type' => AnnouncementType::class,
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'pinned' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // La tarjeta del socio se cachea; cualquier cambio la invalida.
        $forget = fn (Announcement $a) => VisibleAnnouncements::forget($a->gym_id);

        static::saved($forget);
        static::deleted($forget);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Ya empezó y no ha expirado. Fijados primero, luego los más recientes. */
    public function scopeVisible(Builder $query, ?CarbonInterface $at = null): void
    {
        $at ??= now();

        $query->where('starts_at', '<=', $at)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $at))
            ->orderByDesc('pinned')
            ->orderByDesc('starts_at')
            ->orderByDesc('id');
    }

    public function status(?CarbonInterface $at = null): string
    {
        $at ??= now();

        return match (true) {
            $this->starts_at->greaterThan($at) => self::STATUS_SCHEDULED,
            $this->expires_at !== null && $this->expires_at->lessThanOrEqualTo($at) => self::STATUS_EXPIRED,
            default => self::STATUS_ACTIVE,
        };
    }
}
