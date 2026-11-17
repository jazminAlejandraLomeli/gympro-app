<?php

namespace App\Models;

use App\Tenancy\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Valoración física mensual (Módulo D). El primer registro de un socio
 * es su línea base.
 *
 * measured_on se guarda como texto 'Y-m-d' (sin cast de fecha) para que
 * la llave única y las comparaciones funcionen igual en MySQL y SQLite.
 * user_id y recorded_by se asignan explícitamente en el controlador.
 */
#[Fillable(['measured_on', 'weight_kg', 'waist_cm', 'chest_cm', 'arm_cm', 'leg_cm', 'body_fat_pct', 'notes'])]
class BodyMeasurement extends Model
{
    use BelongsToGym;

    /** Columnas numéricas que se grafican y comparan. */
    public const METRICS = ['weight_kg', 'waist_cm', 'chest_cm', 'arm_cm', 'leg_cm', 'body_fat_pct'];

    protected function casts(): array
    {
        return [
            'weight_kg' => 'float',
            'waist_cm' => 'float',
            'chest_cm' => 'float',
            'arm_cm' => 'float',
            'leg_cm' => 'float',
            'body_fat_pct' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
