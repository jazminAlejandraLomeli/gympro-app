<?php

namespace Tests\Feature\Inventory;

use App\Enums\Role;
use App\Models\Announcement;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Tenancy\TenantContext;
use Database\Seeders\Demo\AnnouncementDemoSeeder;
use Database\Seeders\Demo\InventoryDemoSeeder;

class DemoSeederTest extends InventoryTestCase
{
    public function test_demo_seeders_create_consistent_data(): void
    {
        User::factory()->forGym($this->gym)->count(3)->create(['role' => Role::Member]);

        app(TenantContext::class)->runAs($this->gym, function () {
            (new AnnouncementDemoSeeder)->seedGym($this->gym);
            (new InventoryDemoSeeder)->seedGym($this->gym);

            $this->assertSame(4, Announcement::count());
            $this->assertEqualsCanonicalizing(['vigente', 'vigente', 'expirado', 'programado'], Announcement::all()->map->status()->all());
            $this->assertTrue(Announcement::visible()->first()->pinned);

            $this->assertSame(12, Product::count());
            $this->assertSame(3, Product::where('category', 'equipo')->where('is_for_sale', false)->count());
            $this->assertGreaterThan(0, Product::lowStock()->where('is_for_sale', true)->count());
            $this->assertGreaterThan(0, StockMovement::where('type', 'venta')->count());

            // La existencia coincide con la suma de movimientos y nunca es negativa
            foreach (Product::all() as $product) {
                $this->assertSame($product->stock, (int) $product->movements()->sum('quantity'), $product->name);
                $this->assertGreaterThanOrEqual(0, $product->stock);
            }
        });
    }
}
