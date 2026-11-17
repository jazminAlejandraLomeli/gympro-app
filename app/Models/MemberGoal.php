<?php

namespace App\Models;

use App\Tenancy\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Meta semanal de asistencia que el socio elige (Módulo C).
 * Sin registro se usa config('gympro.streaks.weekly_goal').
 * user_id se asigna explícitamente (nunca desde el input).
 */
#[Fillable(['weekly_attendance_goal'])]
class MemberGoal extends Model
{
    use BelongsToGym;

    protected function casts(): array
    {
        return [
            'weekly_attendance_goal' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
