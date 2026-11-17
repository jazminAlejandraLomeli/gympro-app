<?php

namespace App\Models;

use App\Tenancy\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pago registrado en recepción (efectivo, tarjeta en terminal propia o
 * transferencia). Renueva la membresía: ver MembershipBilling.
 *
 * user_id y recorded_by se asignan en el código, nunca del input.
 */
#[Fillable(['amount', 'concept', 'method', 'months_added', 'credits_added', 'paid_at'])]
class MemberPayment extends Model
{
    use BelongsToGym;

    public const METHODS = [
        'efectivo' => 'Efectivo',
        'tarjeta' => 'Tarjeta',
        'transferencia' => 'Transferencia',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'months_added' => 'integer',
            'credits_added' => 'integer',
            'paid_at' => 'datetime',
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
