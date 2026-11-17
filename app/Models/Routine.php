<?php

namespace App\Models;

use App\Tenancy\BelongsToGym;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rutina de un solo día (type = single) o de la semana (type = weekly).
 *
 * Es plantilla del gimnasio (is_template = true, member_id = NULL) o
 * rutina personal de un socio (member_id = socio), nunca ambas.
 * is_template, member_id y created_by NO son fillable.
 */
#[Fillable(['name', 'type', 'goal', 'notes'])]
class Routine extends Model
{
    use BelongsToGym;

    public const TYPE_SINGLE = 'single';

    public const TYPE_WEEKLY = 'weekly';

    public const TYPES = [
        self::TYPE_SINGLE => 'Un día',
        self::TYPE_WEEKLY => 'Semanal',
    ];

    /** Etiquetas cortas de los días ISO (1 = lunes). */
    public const DAY_SHORT = [1 => 'L', 2 => 'M', 3 => 'X', 4 => 'J', 5 => 'V', 6 => 'S', 7 => 'D'];

    public const DAY_NAMES = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    protected $attributes = [
        'type' => self::TYPE_WEEKLY,
        'is_template' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_template' => 'boolean',
            'member_id' => 'integer',
            'created_by' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(RoutineItem::class)->orderBy('day_of_week')->orderBy('position');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(RoutineAssignment::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeTemplates(Builder $query): void
    {
        $query->where('is_template', true);
    }

    public function scopePersonal(Builder $query): void
    {
        $query->where('is_template', false);
    }

    /** Rutinas con una asignación vigente (semanal activa o de un día de hoy en adelante). */
    public function scopeWithIsCurrent(Builder $query, CarbonInterface $today): void
    {
        $query->withExists(['assignments as is_current' => fn (Builder $q) => $q
            ->where('active', true)
            ->where(fn (Builder $q) => $q->whereNull('scheduled_for')->orWhere('scheduled_for', '>=', $today->toDateString())),
        ]);
    }

    public function isSingle(): bool
    {
        return $this->type === self::TYPE_SINGLE;
    }

    /** La armó el mismo socio (no un coach). */
    public function isCreatedByMember(): bool
    {
        return $this->member_id !== null && $this->created_by === $this->member_id;
    }

    /** "Coach Ana" o "Creada por el socio" para el panel. */
    public function authorLabel(): string
    {
        if ($this->isCreatedByMember()) {
            return 'Creada por el socio';
        }

        return $this->creator?->name ?? 'Personal del gimnasio';
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
