<?php

namespace App\Models;

use App\Tenancy\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rutina asignada a un socio. Usa App\Services\Training\RoutineAssigner:
 *  - semanal (scheduled_for NULL): una sola activa por socio.
 *  - de un día (scheduled_for = fecha): una sola activa por socio y fecha.
 *
 * user_id y assigned_by NO son fillable (se asignan explícitamente).
 * scheduled_for se guarda como texto 'Y-m-d' (sin cast de fecha) para
 * poder compararlo con where() y aprovechar el índice en cualquier BD.
 */
#[Fillable(['routine_id', 'starts_on', 'scheduled_for', 'active'])]
class RoutineAssignment extends Model
{
    use BelongsToGym;

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'active' => 'boolean',
            'user_id' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    public function scopeWeekly(Builder $query): void
    {
        $query->whereNull('scheduled_for');
    }

    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /** Vigente: semanal activa, o de un día activa con fecha de hoy en adelante. */
    public function isCurrent(string $today): bool
    {
        if (! $this->active) {
            return false;
        }

        return $this->scheduled_for === null || $this->scheduled_for >= $today;
    }
}
