<?php

namespace App\Models;

use App\Tenancy\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Rutina semanal (plantilla) que se asigna a uno o varios socios. */
#[Fillable(['name', 'goal', 'notes'])]
class Routine extends Model
{
    use BelongsToGym;

    /** Etiquetas cortas de los días ISO (1 = lunes). */
    public const DAY_SHORT = [1 => 'L', 2 => 'M', 3 => 'X', 4 => 'J', 5 => 'V', 6 => 'S', 7 => 'D'];

    public const DAY_NAMES = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    public function items(): HasMany
    {
        return $this->hasMany(RoutineItem::class)->orderBy('day_of_week')->orderBy('position');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RoutineAssignment::class);
    }

    public function activeAssignments(): HasMany
    {
        return $this->assignments()->where('active', true);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
