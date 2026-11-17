<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Tenancy\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Movimiento de existencias (Módulo F). Solo se crean, nunca se editan.
 * `quantity` lleva signo: positivo entra, negativo sale.
 * user_id (personal que lo registró) se asigna en el servicio.
 */
#[Fillable(['product_id', 'type', 'quantity', 'unit_price', 'total', 'note', 'member_id'])]
class StockMovement extends Model
{
    use BelongsToGym;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }
}
