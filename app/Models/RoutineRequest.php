<?php

namespace App\Models;

use App\Tenancy\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Solicitud de rutina de un socio a los coaches. Máximo una pendiente
 * por socio. user_id, status, routine_id y handled_* NO son fillable.
 */
#[Fillable(['kind', 'goal', 'days_per_week'])]
class RoutineRequest extends Model
{
    use BelongsToGym;

    public const PENDING = 'pending';

    public const DONE = 'done';

    public const CANCELLED = 'cancelled';

    protected $attributes = [
        'status' => self::PENDING,
    ];

    protected function casts(): array
    {
        return [
            'days_per_week' => 'integer',
            'handled_at' => 'datetime',
            'user_id' => 'integer',
        ];
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', self::PENDING);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    /** Queda atendida con la rutina que se le asignó al socio. */
    public function markDone(Routine $routine, ?User $by): void
    {
        $this->forceFill([
            'status' => self::DONE,
            'routine_id' => $routine->id,
            'handled_by' => $by?->id,
            'handled_at' => now(),
        ])->save();
    }

    public function markCancelled(?User $by): void
    {
        $this->forceFill([
            'status' => self::CANCELLED,
            'handled_by' => $by?->id,
            'handled_at' => now(),
        ])->save();
    }

    public function toClientArray(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'kind_label' => Routine::TYPES[$this->kind] ?? $this->kind,
            'goal' => $this->goal,
            'days_per_week' => $this->days_per_week,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
