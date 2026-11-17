<?php

namespace App\Services\Inventory;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Venta rápida en mostrador.
 *
 * Todo en una transacción con lockForUpdate sobre los productos: dos
 * cajas cobrando el último producto a la vez no dejan stock negativo.
 * Si a un renglón le falta existencia, no se cobra nada y se responde
 * con un error por producto.
 */
class RecordCounterSale
{
    /**
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     * @return array{movements: Collection<int, StockMovement>, total: string}
     *
     * @throws ValidationException
     */
    public function handle(User $staff, array $items, ?int $memberId = null): array
    {
        // Un renglón por producto aunque el carrito lo traiga repetido
        $quantities = [];
        foreach ($items as $item) {
            $id = (int) $item['product_id'];
            $quantities[$id] = ($quantities[$id] ?? 0) + (int) $item['quantity'];
        }

        return DB::transaction(function () use ($staff, $quantities, $memberId) {
            // El TenantScope aplica: productos de otro gimnasio no aparecen
            $products = Product::query()
                ->whereIn('id', array_keys($quantities))
                ->orderBy('id') // mismo orden de bloqueo siempre: evita deadlocks
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $errors = [];
            foreach ($quantities as $id => $qty) {
                $product = $products->get($id);

                if (! $product || ! $product->active || ! $product->is_for_sale) {
                    $errors["product_{$id}"] = $product
                        ? "{$product->name} no está a la venta."
                        : 'Uno de los productos ya no existe.';

                    continue;
                }

                if ($product->stock < $qty) {
                    $errors["product_{$id}"] = $product->stock > 0
                        ? "{$product->name}: solo quedan {$product->stock} y pediste {$qty}."
                        : "{$product->name}: sin existencias.";
                }
            }

            if ($errors) {
                throw ValidationException::withMessages(['items' => array_values($errors)] + $errors);
            }

            $movements = collect();
            $totalCents = 0;

            foreach ($quantities as $id => $qty) {
                $product = $products->get($id);
                $priceCents = (int) round(((float) $product->price) * 100);
                $lineCents = $priceCents * $qty;
                $totalCents += $lineCents;

                $product->stock -= $qty;
                $product->save();

                $movement = new StockMovement([
                    'product_id' => $product->id,
                    'type' => StockMovementType::Venta,
                    'quantity' => -$qty,
                    'unit_price' => $priceCents / 100,
                    'total' => $lineCents / 100,
                    'member_id' => $memberId,
                    'note' => null,
                ]);
                $movement->user_id = $staff->id;
                $movement->save();

                $movements->push($movement);
            }

            return ['movements' => $movements, 'total' => number_format($totalCents / 100, 2, '.', '')];
        });
    }
}
