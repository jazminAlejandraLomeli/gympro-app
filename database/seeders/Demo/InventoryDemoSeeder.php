<?php

namespace Database\Seeders\Demo;

use App\Enums\ProductCategory;
use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Models\Gym;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Carbon;

/** Módulo F: catálogo, equipo y ventas de los últimos días. */
class InventoryDemoSeeder
{
    public function seedGym(Gym $gym): void
    {
        $admin = User::query()->where('role', Role::GymAdmin)->first();
        $members = User::query()->where('role', Role::Member)->pluck('id');

        // [sku, nombre, categoría, en venta, precio, costo, existencia inicial, mínimo]
        $catalog = [
            ['PROT-VAI-1KG', 'Proteína whey vainilla 1 kg', ProductCategory::Suplemento, true, 899, 620, 14, 4],
            ['PROT-CHO-1KG', 'Proteína whey chocolate 1 kg', ProductCategory::Suplemento, true, 899, 620, 6, 4],
            ['PROT-SCOOP', 'Scoop de proteína', ProductCategory::Suplemento, true, 35, 18, 80, 20],
            ['CREA-300', 'Creatina 300 g', ProductCategory::Suplemento, true, 549, 380, 3, 3],
            ['AGUA-1L', 'Agua natural 1 L', ProductCategory::Bebida, true, 20, 8, 60, 24],
            ['ISO-600', 'Bebida isotónica 600 ml', ProductCategory::Bebida, true, 30, 15, 30, 12],
            ['ENER-473', 'Bebida energética 473 ml', ProductCategory::Bebida, true, 45, 24, 8, 10],
            ['TOALLA', 'Toalla de microfibra', ProductCategory::Accesorio, true, 150, 70, 12, 3],
            ['GUANT-M', 'Guantes de entrenamiento talla M', ProductCategory::Accesorio, true, 280, 140, 2, 3],
            ['CAMINADORA', 'Caminadora comercial', ProductCategory::Equipo, false, 0, 45000, 4, 0],
            ['MANC-SET', 'Juego de mancuernas 2.5–25 kg', ProductCategory::Equipo, false, 0, 32000, 1, 0],
            ['BANCO-AJ', 'Banco ajustable', ProductCategory::Equipo, false, 0, 6500, 6, 0],
        ];

        $products = [];
        foreach ($catalog as [$sku, $name, $category, $forSale, $price, $cost, $initial, $min]) {
            $product = Product::create([
                'sku' => $sku,
                'name' => $name,
                'category' => $category,
                'is_for_sale' => $forSale,
                'price' => $price,
                'cost' => $cost,
                'min_stock' => $min,
                'active' => true,
            ]);

            $this->movement($product, StockMovementType::Entrada, $initial, $admin?->id, null, 'Existencia inicial', now()->subDays(8));
            $products[] = $product;
        }

        // Ventas de los últimos 6 días (incluido hoy), sin agotar existencias
        $forSale = array_values(array_filter($products, fn (Product $p) => $p->is_for_sale));
        mt_srand($gym->id * 97);

        for ($daysAgo = 6; $daysAgo >= 0; $daysAgo--) {
            $sales = mt_rand(3, 7);
            for ($i = 0; $i < $sales; $i++) {
                $product = $forSale[mt_rand(0, count($forSale) - 1)];
                $qty = $product->price < 100 ? mt_rand(1, 3) : 1;

                if ($product->stock - $qty <= $product->min_stock + 1 && ! in_array($product->sku, ['ENER-473', 'GUANT-M', 'CREA-300'], true)) {
                    continue; // deja "Stock bajo" solo en los productos elegidos
                }
                if ($product->stock < $qty) {
                    continue;
                }

                $at = now()->subDays($daysAgo)->setTime(mt_rand(7, 20), mt_rand(0, 59));
                if ($at->isFuture()) {
                    $at = now()->subMinutes(mt_rand(5, 120));
                }

                $memberId = $members->isNotEmpty() && mt_rand(0, 1) ? $members->random() : null;
                $this->movement($product, StockMovementType::Venta, $qty, $admin?->id, $memberId, null, $at);
            }
        }
    }

    private function movement(Product $product, StockMovementType $type, int $qty, ?int $userId, ?int $memberId, ?string $note, Carbon $at): void
    {
        $delta = $type === StockMovementType::Venta ? -$qty : $qty;

        $movement = new StockMovement([
            'product_id' => $product->id,
            'type' => $type,
            'quantity' => $delta,
            'unit_price' => $type === StockMovementType::Venta ? $product->price : $product->cost,
            'total' => $type === StockMovementType::Venta ? round((float) $product->price * $qty, 2) : null,
            'note' => $note,
            'member_id' => $memberId,
        ]);
        $movement->user_id = $userId;
        $movement->created_at = $at;
        $movement->save();

        $product->stock += $delta;
        $product->save();
    }
}
