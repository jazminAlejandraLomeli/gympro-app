<?php

namespace App\Services\Inventory;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Entradas, salidas y ajustes manuales de existencias.
 *
 *  - entrada: suma `quantity` (reabasto).
 *  - salida:  resta `quantity` (merma, uso interno). No puede dejar negativo.
 *  - ajuste:  `quantity` es la existencia real contada; se guarda la diferencia.
 */
class AdjustStock
{
    /** @throws ValidationException */
    public function handle(User $staff, Product $product, StockMovementType $type, int $quantity, ?string $note = null): StockMovement
    {
        if ($type === StockMovementType::Venta) {
            throw new \InvalidArgumentException('Las ventas se registran con RecordCounterSale.');
        }

        return DB::transaction(function () use ($staff, $product, $type, $quantity, $note) {
            /** @var Product $locked */
            $locked = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();

            $delta = match ($type) {
                StockMovementType::Entrada => $quantity,
                StockMovementType::Salida => -$quantity,
                StockMovementType::Ajuste => $quantity - $locked->stock,
            };

            if ($locked->stock + $delta < 0) {
                throw ValidationException::withMessages([
                    'quantity' => "Solo hay {$locked->stock} en existencia; no puedes sacar {$quantity}.",
                ]);
            }

            if ($delta === 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'La existencia contada es igual a la del sistema; no hay nada que ajustar.',
                ]);
            }

            $locked->stock += $delta;
            $locked->save();

            $movement = new StockMovement([
                'product_id' => $locked->id,
                'type' => $type,
                'quantity' => $delta,
                'unit_price' => $type === StockMovementType::Entrada ? $locked->cost : null,
                'total' => null,
                'note' => $note,
            ]);
            $movement->user_id = $staff->id;
            $movement->save();

            $product->setRawAttributes($locked->getAttributes(), true);

            return $movement;
        });
    }
}
