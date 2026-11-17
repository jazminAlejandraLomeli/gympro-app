<?php

namespace App\Models;

use App\Tenancy\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de entrada (check-in). Tabla compartida:
 *  - Módulo A la escribe al escanear el QR.
 *  - Módulo C la lee para el calendario semanal y las rachas.
 *  - Módulo 0 la cuenta para "escaneos del día".
 */
#[Fillable(['user_id', 'scanned_at', 'method', 'credits_used'])]
class Attendance extends Model
{
    use BelongsToGym;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
            'credits_used' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
