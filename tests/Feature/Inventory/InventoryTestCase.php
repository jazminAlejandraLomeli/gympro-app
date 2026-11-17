<?php

namespace Tests\Feature\Inventory;

use App\Enums\ProductCategory;
use App\Enums\Role;
use App\Models\Gym;
use App\Models\Product;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class InventoryTestCase extends TestCase
{
    use RefreshDatabase;

    protected Gym $gym;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->gym = Gym::factory()->create(['slug' => 'irondemo']);
        $this->admin = User::factory()->forGym($this->gym)->role(Role::GymAdmin)->create();
    }

    protected function url(string $path = ''): string
    {
        return "http://irondemo.localhost/panel/inventario{$path}";
    }

    /** Crea un producto con existencia directa (sin movimiento) dentro de un gimnasio. */
    protected function product(array $attributes = [], int $stock = 10, ?Gym $gym = null): Product
    {
        return app(TenantContext::class)->runAs($gym ?? $this->gym, function () use ($attributes, $stock) {
            $product = new Product(array_merge([
                'name' => 'Agua 1 L',
                'category' => ProductCategory::Bebida,
                'is_for_sale' => true,
                'price' => 20,
                'min_stock' => 2,
            ], $attributes));
            $product->stock = $stock;
            $product->save();

            return $product;
        });
    }
}
