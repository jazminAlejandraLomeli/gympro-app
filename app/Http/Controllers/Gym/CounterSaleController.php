<?php

namespace App\Http\Controllers\Gym;

use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreCounterSaleRequest;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Inventory\RecordCounterSale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Venta rápida en mostrador (pantalla principal de Inventario). */
class CounterSaleController extends Controller
{
    public function index(): Response
    {
        $products = Product::query()
            ->forSale()
            ->orderBy('category')
            ->orderBy('name')
            ->get(['id', 'name', 'category', 'price', 'stock', 'min_stock'])
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'category' => $p->category->value,
                'categoryLabel' => $p->category->label(),
                'price' => $p->price,
                'stock' => $p->stock,
                'lowStock' => $p->isLowStock(),
            ]);

        $todaySales = StockMovement::query()
            ->where('type', StockMovementType::Venta)
            ->where('created_at', '>=', today());

        $recent = (clone $todaySales)
            ->with(['product:id,name', 'member:id,name'])
            ->latest('created_at')
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (StockMovement $m) => [
                'id' => $m->id,
                'product' => $m->product?->name,
                'quantity' => abs($m->quantity),
                'total' => $m->total,
                'member' => $m->member?->name,
                'created_at' => $m->created_at->toIso8601String(),
            ]);

        return Inertia::render('Gym/Inventory/CounterSale', [
            'products' => $products,
            'today' => [
                'total' => number_format((float) (clone $todaySales)->sum('total'), 2, '.', ''),
                'items' => (int) -(clone $todaySales)->sum('quantity'),
                'recent' => $recent,
            ],
        ]);
    }

    public function store(StoreCounterSaleRequest $request, RecordCounterSale $sale): RedirectResponse
    {
        $result = $sale->handle(
            $request->user(),
            $request->validated('items'),
            $request->validated('member_id'),
        );

        $total = '$'.number_format((float) $result['total'], 2);

        return to_route('gym.inventory.index')->with('success', "Venta registrada por {$total}.");
    }

    /** Búsqueda de socios para asociar la venta (JSON). */
    public function members(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q'));

        if (mb_strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $members = User::query()
            ->where('role', Role::Member)
            ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like))
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'email']);

        return response()->json(['data' => $members]);
    }
}
