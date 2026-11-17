<?php

namespace App\Models;

use App\Enums\ProductCategory;
use App\Tenancy\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Producto o equipo del inventario (Módulo F).
 *
 * `stock` NO es fillable: solo cambia mediante movimientos
 * (App\Services\Inventory\*) para que siempre haya historial.
 * El equipamiento del gimnasio es category=equipo, is_for_sale=false.
 */
#[Fillable(['sku', 'name', 'category', 'is_for_sale', 'price', 'cost', 'min_stock', 'active', 'notes'])]
class Product extends Model
{
    use BelongsToGym;

    protected $attributes = [
        'is_for_sale' => true,
        'price' => 0,
        'stock' => 0,
        'min_stock' => 0,
        'active' => true,
    ];

    protected function casts(): array
    {
        return [
            'category' => ProductCategory::class,
            'is_for_sale' => 'boolean',
            'active' => 'boolean',
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
            'stock' => 'integer',
            'min_stock' => 'integer',
        ];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isLowStock(): bool
    {
        return $this->stock <= $this->min_stock;
    }

    public function scopeLowStock(Builder $query): void
    {
        $query->whereColumn('stock', '<=', 'min_stock');
    }

    /** Lo que aparece en la venta rápida del mostrador. */
    public function scopeForSale(Builder $query): void
    {
        $query->where('active', true)->where('is_for_sale', true);
    }
}
