<?php

namespace Tests\Feature\Inventory;

use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Models\Gym;
use App\Models\StockMovement;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

class CounterSaleTest extends InventoryTestCase
{
    public function test_sale_decrements_stock_and_records_one_movement_per_line(): void
    {
        $water = $this->product(['name' => 'Agua', 'price' => 20], stock: 10);
        $protein = $this->product(['name' => 'Proteína', 'price' => 899.5], stock: 3);
        $member = User::factory()->forGym($this->gym)->create();

        $this->actingAs($this->admin)->post($this->url('/ventas'), [
            'items' => [
                ['product_id' => $water->id, 'quantity' => 3],
                ['product_id' => $protein->id, 'quantity' => 2],
            ],
            'member_id' => $member->id,
        ])->assertRedirect($this->url())->assertSessionHas('success');

        $this->assertSame(7, $water->fresh()->stock);
        $this->assertSame(1, $protein->fresh()->stock);

        $movements = StockMovement::withoutGlobalScopes()->orderBy('product_id')->get();
        $this->assertCount(2, $movements);
        $this->assertTrue($movements->every(fn ($m) => $m->type === StockMovementType::Venta
            && $m->gym_id === $this->gym->id
            && $m->user_id === $this->admin->id
            && $m->member_id === $member->id));
        $this->assertSame([-3, -2], $movements->pluck('quantity')->all());
        $this->assertSame(['60.00', '1799.00'], $movements->pluck('total')->all());

        // Total del día en la pantalla de venta
        $this->actingAs($this->admin)->get($this->url())
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Gym/Inventory/CounterSale')
                ->where('today.total', '1859.00')
                ->where('today.items', 5)
                ->has('products', 2));
    }

    public function test_sale_without_enough_stock_fails_and_changes_nothing(): void
    {
        $water = $this->product(['name' => 'Agua'], stock: 10);
        $towel = $this->product(['name' => 'Toalla', 'price' => 150], stock: 1);

        $this->actingAs($this->admin)->from($this->url())->post($this->url('/ventas'), [
            'items' => [
                ['product_id' => $water->id, 'quantity' => 2],
                ['product_id' => $towel->id, 'quantity' => 3],
            ],
        ])->assertRedirect($this->url())
            ->assertSessionHasErrors(["product_{$towel->id}" => 'Toalla: solo quedan 1 y pediste 3.']);

        $this->assertSame(10, $water->fresh()->stock);
        $this->assertSame(1, $towel->fresh()->stock);
        $this->assertSame(0, StockMovement::withoutGlobalScopes()->count());
    }

    public function test_cannot_sell_equipment_or_inactive_products(): void
    {
        $equipment = $this->product(['name' => 'Caminadora', 'category' => 'equipo', 'is_for_sale' => false, 'price' => 0], stock: 4);

        $this->actingAs($this->admin)->post($this->url('/ventas'), [
            'items' => [['product_id' => $equipment->id, 'quantity' => 1]],
        ])->assertSessionHasErrors("product_{$equipment->id}");

        $this->assertSame(4, $equipment->fresh()->stock);
    }

    public function test_cannot_sell_a_product_from_another_gym(): void
    {
        $other = Gym::factory()->create();
        $foreign = $this->product(['name' => 'Ajeno'], stock: 10, gym: $other);

        $this->actingAs($this->admin)->post($this->url('/ventas'), [
            'items' => [['product_id' => $foreign->id, 'quantity' => 1]],
        ])->assertSessionHasErrors('items.0.product_id');

        $this->assertSame(10, $foreign->fresh()->stock);
        $this->assertSame(0, StockMovement::withoutGlobalScopes()->count());
    }

    public function test_cannot_attach_a_member_from_another_gym(): void
    {
        $water = $this->product(stock: 5);
        $otherGym = Gym::factory()->create();
        $foreignMember = User::factory()->forGym($otherGym)->create();

        $this->actingAs($this->admin)->post($this->url('/ventas'), [
            'items' => [['product_id' => $water->id, 'quantity' => 1]],
            'member_id' => $foreignMember->id,
        ])->assertSessionHasErrors('member_id');

        $this->assertSame(5, $water->fresh()->stock);
    }

    public function test_member_search_only_returns_members_of_this_gym(): void
    {
        User::factory()->forGym($this->gym)->create(['name' => 'Ana López']);
        User::factory()->forGym($this->gym)->role(Role::Trainer)->create(['name' => 'Ana Coach']);
        User::factory()->forGym(Gym::factory()->create())->create(['name' => 'Ana Ajena']);

        $this->actingAs($this->admin)->getJson($this->url('/socios?q=Ana'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Ana López');
    }

    public function test_trainer_and_member_cannot_use_inventory(): void
    {
        $water = $this->product(stock: 5);
        $trainer = User::factory()->forGym($this->gym)->role(Role::Trainer)->create();
        $member = User::factory()->forGym($this->gym)->create();

        foreach ([$trainer, $member] as $user) {
            $this->actingAs($user)->get($this->url())->assertForbidden();
            $this->actingAs($user)->get($this->url('/productos'))->assertForbidden();
            $this->actingAs($user)->post($this->url('/ventas'), [
                'items' => [['product_id' => $water->id, 'quantity' => 1]],
            ])->assertForbidden();
            $this->actingAs($user)->post($this->url("/productos/{$water->id}/movimientos"), [
                'type' => 'entrada', 'quantity' => 5,
            ])->assertForbidden();
        }

        $this->assertSame(5, $water->fresh()->stock);
    }
}
