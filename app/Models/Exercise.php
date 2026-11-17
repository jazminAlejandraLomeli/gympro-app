<?php

namespace App\Models;

use App\Tenancy\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Ejercicio del catálogo del gimnasio, con un clip corto opcional.
 *
 * El clip lo maneja el job CompressExerciseClip: clip_path, clip_status
 * y clip_size_bytes NO son fillable (se asignan con forceFill).
 */
#[Fillable(['name', 'muscle_group', 'instructions'])]
class Exercise extends Model
{
    use BelongsToGym;

    public const CLIP_NONE = 'none';

    public const CLIP_PROCESSING = 'processing';

    public const CLIP_READY = 'ready';

    public const CLIP_FAILED = 'failed';

    /** Grupos musculares (clave => etiqueta). */
    public const MUSCLE_GROUPS = [
        'pecho' => 'Pecho',
        'espalda' => 'Espalda',
        'hombros' => 'Hombros',
        'biceps' => 'Bíceps',
        'triceps' => 'Tríceps',
        'piernas' => 'Piernas',
        'gluteos' => 'Glúteos',
        'pantorrillas' => 'Pantorrillas',
        'abdomen' => 'Abdomen',
        'cardio' => 'Cardio',
        'cuerpo_completo' => 'Cuerpo completo',
    ];

    protected $attributes = [
        'clip_status' => self::CLIP_NONE,
    ];

    protected function casts(): array
    {
        return [
            'clip_size_bytes' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function routineItems(): HasMany
    {
        return $this->hasMany(RoutineItem::class);
    }

    public function muscleGroupLabel(): string
    {
        return self::MUSCLE_GROUPS[$this->muscle_group] ?? $this->muscle_group;
    }

    /** URL pública del clip comprimido (puede existir aunque se esté procesando uno nuevo). */
    public function clipUrl(): ?string
    {
        return $this->clip_path ? Storage::disk('public')->url($this->clip_path) : null;
    }

    /** Datos mínimos para el navegador. */
    public function toClientArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'muscle_group' => $this->muscle_group,
            'muscle_group_label' => $this->muscleGroupLabel(),
            'instructions' => $this->instructions,
            'clip_url' => $this->clipUrl(),
            'clip_status' => $this->clip_status,
            'clip_size_bytes' => $this->clip_size_bytes,
        ];
    }
}
