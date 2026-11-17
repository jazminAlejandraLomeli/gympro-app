<?php

namespace App\Models;

use App\Tenancy\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un ejercicio dentro de un día de la rutina. */
#[Fillable(['day_of_week', 'exercise_id', 'position', 'sets', 'reps', 'weight_kg', 'rest_seconds'])]
class RoutineItem extends Model
{
    use BelongsToGym;

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'position' => 'integer',
            'sets' => 'integer',
            'weight_kg' => 'decimal:2',
            'rest_seconds' => 'integer',
        ];
    }

    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
