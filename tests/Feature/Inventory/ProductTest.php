<?php

namespace Tests\Feature\Inventory;

use App\Enums\StockMovementType;
use App\Models\Gym;
use App\Models\Product;
use App\Models\StockMovement;
use Inertia\Testing\AssertableInertia;

class ProductTest extends InventoryTestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'sku' => 'prot-vai',
            'name' => 'Proteína vainilla',
            'category' => 'suplemento',
            'is_for_sale' => true,
            'price' => '899.00',
            'cost' => '620',
            'min_stock' => 4,
            'initial_stock' => 12,
        ], $overrides);
    }

    public function test_admin_creates_product_with_initial_stock_movement(): void
    {
        $this->actingAs($this->admin)->post($this->url('/productos'), $this->payload())
            ->assertRedirect($this->url('/productos'));

        $product = Product::withoutGlobalScopes()->sole();
        $this->assertSame($this->gym->id, $product->gym_id);
        $this->assertSame('PROT-VAI', $product->sku);
        $this->assertSame(12, $product->stock);

        $movement = StockMovement::withoutGlobalScopes()->sole();
        $this->assertSame(StockMovementType::Entrada, $movement->type);
        $this->assertSame(12, $movement->quantity);
    }

    public function test_sku_is_unique_per_gym_but_can_repeat_in_another_gym(): void
    {
        $this->product(['sku' => 'PROT-VAI']);
        $this->product(['sku' => 'AGUA-1L'], gym: Gym::factory()->create());

        $this->actingAs($this->admin)->post($this->url('/productos'), $this->payload(['sku' => 'prot-vai']))
            ->assertSessionHasErrors(['sku' => 'Ya tienes un producto con ese SKU.']);

        // Mismo SKU que otro gimnasio: permitido
        $this->actingAs($this->admin)->post($this->url('/productos'), $this->payload(['sku' => 'AGUA-1L']))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Product::withoutGlobalScopes()->where('sku', 'AGUA-1L')->count());
    }

    public function test_editing_keeps_its_own_sku(): void
    {
        $product = $this->product(['sku' => 'PROT-VAI']);

        $this->actingAs($this->admin)->put($this->url("/productos/{$product->id}"), $this->payload(['name' => 'Renombrado']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Renombrado', $product->fresh()->name);
        $this->assertSame(10, $product->fresh()->stock); // la edición no toca existencias
    }

    public function test_equipment_is_never_for_sale(): void
    {
        $this->actingAs($this->admin)->post($this->url('/productos'), $this->payload([
            'sku' => 'CAMINADORA', 'category' => 'equipo', 'is_for_sale' => true, 'price' => '',
        ]))->assertSessionHasNoErrors();

        $this->assertFalse(Product::withoutGlobalScopes()->sole()->is_for_sale);
    }

    public function test_entry_exit_and_adjustment_update_stock_with_movements(): void
    {
        $product = $this->product(stock: 10);
        $url = $this->url("/productos/{$product->id}/movimientos");

        $this->actingAs($this->admin)->post($url, ['type' => 'entrada', 'quantity' => 5])->assertSessionHasNoErrors();
        $this->assertSame(15, $product->fresh()->stock);

        $this->actingAs($this->admin)->post($url, ['type' => 'salida', 'quantity' => 3])->assertSessionHasErrors('note');
        $this->actingAs($this->admin)->post($url, ['type' => 'salida', 'quantity' => 3, 'note' => 'Merma'])->assertSessionHasNoErrors();
        $this->assertSame(12, $product->fresh()->stock);

        $this->actingAs($this->admin)->post($url, ['type' => 'salida', 'quantity' => 50, 'note' => 'Error'])->assertSessionHasErrors('quantity');
        $this->assertSame(12, $product->fresh()->stock);

        // Ajuste por conteo físico: se guarda la diferencia
        $this->actingAs($this->admin)->post($url, ['type' => 'ajuste', 'quantity' => 9, 'note' => 'Conteo'])->assertSessionHasNoErrors();
        $this->assertSame(9, $product->fresh()->stock);

        $this->actingAs($this->admin)->post($url, ['type' => 'venta', 'quantity' => 1])->assertSessionHasErrors('type');

        $this->assertSame([5, -3, -3], StockMovement::withoutGlobalScopes()->orderBy('id')->pluck('quantity')->all());
    }

    public function test_low_stock_filter_and_toggle_active(): void
    {
        $low = $this->product(['name' => 'Guantes', 'min_stock' => 3], stock: 2);
        $this->product(['name' => 'Agua', 'min_stock' => 3], stock: 30);

        $this->actingAs($this->admin)->get($this->url('/productos?low=1'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Gym/Inventory/Products/Index')
                ->has('products.data', 1)
                ->where('products.data.0.name', 'Guantes')
                ->where('products.data.0.lowStock', true)
                ->where('lowStockCount', 1));

        $this->actingAs($this->admin)->patch($this->url("/productos/{$low->id}/activo"))->assertRedirect();
        $this->assertFalse($low->fresh()->active);
    }

    public function test_movement_history_is_paginated_and_filterable(): void
    {
        $product = $this->product(stock: 0);
        $url = $this->url("/productos/{$product->id}/movimientos");
        $this->actingAs($this->admin)->post($url, ['type' => 'entrada', 'quantity' => 5]);
        $this->actingAs($this->admin)->post($url, ['type' => 'salida', 'quantity' => 1, 'note' => 'Merma']);

        $this->actingAs($this->admin)->get($this->url("/movimientos?type=salida&product={$product->id}"))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Gym/Inventory/Movements')
                ->has('movements.data', 1)
                ->where('movements.data.0.note', 'Merma'));
    }

    public function test_cannot_touch_products_of_another_gym(): void
    {
        $foreign = $this->product(['name' => 'Ajeno'], stock: 5, gym: Gym::factory()->create());

        $this->actingAs($this->admin)->get($this->url("/productos/{$foreign->id}/editar"))->assertNotFound();
        $this->actingAs($this->admin)->post($this->url("/productos/{$foreign->id}/movimientos"), ['type' => 'entrada', 'quantity' => 1])->assertNotFound();
        $this->assertSame(5, $foreign->fresh()->stock);
    }
}
