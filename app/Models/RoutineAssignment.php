<?php

namespace App\Models;

use App\Tenancy\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rutina asignada a un socio. Solo una activa por socio: usa
 * App\Services\Training\RoutineAssigner para asignar.
 */
#[Fillable(['routine_id', 'user_id', 'starts_on', 'active'])]
class RoutineAssignment extends Model
{
    use BelongsToGym;

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
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
}
