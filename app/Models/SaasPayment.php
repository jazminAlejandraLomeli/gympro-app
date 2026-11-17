<?php

namespace App\Models;

use App\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pago de la licencia SaaS de un gimnasio (cobro pasivo, registro manual).
 *
 * Dato central del SaaS: NO usa BelongsToGym. Solo lo consulta el
 * super_admin desde el dominio central. gym_id y recorded_by se asignan
 * explícitamente (ver App\Services\Saas\RecordSaasPayment).
 */
#[Fillable(['amount', 'months', 'paid_at', 'covers_until', 'method', 'notes'])]
class SaasPayment extends Model
{
    public const METHODS = [
        'transferencia' => 'Transferencia',
        'deposito' => 'Depósito',
        'efectivo' => 'Efectivo',
        'tarjeta' => 'Tarjeta',
        'otro' => 'Otro',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'months' => 'integer',
            'paid_at' => 'date',
            'covers_until' => 'date',
        ];
    }

    public function gym(): BelongsTo
    {
        return $this->belongsTo(Gym::class);
    }

    public function recorder(): BelongsTo
    {
        // El super_admin tiene gym_id NULL: sin el filtro por gimnasio
        return $this->belongsTo(User::class, 'recorded_by')->withoutGlobalScope(TenantScope::class);
    }
}
