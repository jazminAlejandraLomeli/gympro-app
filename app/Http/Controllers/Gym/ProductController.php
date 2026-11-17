<?php

namespace App\Http\Controllers\Gym;

use App\Enums\ProductCategory;
use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\SaveProductRequest;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Inventory\AdjustStock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Catálogo de productos y equipo del gimnasio (Módulo F). */
class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'category' => ['nullable', Rule::enum(ProductCategory::class)],
            'low' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:80'],
            'inactive' => ['nullable', 'boolean'],
        ]);

        $products = Product::query()
            ->when($filters['category'] ?? null, fn ($q, $c) => $q->where('category', $c))
            ->when($request->boolean('low'), fn ($q) => $q->lowStock())
            ->when(! $request->boolean('inactive'), fn ($q) => $q->where('active', true))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('sku', 'like', $like));
            })
            ->orderByDesc('active')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Product $p) => $this->present($p));

        return Inertia::render('Gym/Inventory/Products/Index', [
            'products' => $products,
            'filters' => [
                'category' => $filters['category'] ?? null,
                'low' => $request->boolean('low'),
                'q' => $filters['q'] ?? '',
                'inactive' => $request->boolean('inactive'),
            ],
            'categories' => ProductCategory::options(),
            'lowStockCount' => Product::query()->where('active', true)->lowStock()->count(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Gym/Inventory/Products/Form', [
            'product' => null,
            'categories' => ProductCategory::options(),
            'movementTypes' => [],
            'movements' => [],
        ]);
    }

    public function store(SaveProductRequest $request, AdjustStock $adjust): RedirectResponse
    {
        $product = DB::transaction(function () use ($request, $adjust) {
            $product = Product::create($request->safe()->except('initial_stock'));

            $initial = (int) $request->validated('initial_stock', 0);
            if ($initial > 0) {
                $adjust->handle($request->user(), $product, StockMovementType::Entrada, $initial, 'Existencia inicial');
            }

            return $product;
        });

        return to_route('gym.inventory.products.index')->with('success', "Producto «{$product->name}» dado de alta.");
    }

    public function edit(Product $product): Response
    {
        $movements = $product->movements()
            ->with(['user:id,name', 'member:id,name'])
            ->latest('created_at')
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn (StockMovement $m) => StockMovementController::present($m));

        return Inertia::render('Gym/Inventory/Products/Form', [
            'product' => $this->present($product),
            'categories' => ProductCategory::options(),
            'movementTypes' => array_values(array_filter(
                StockMovementType::options(),
                fn ($o) => $o['value'] !== StockMovementType::Venta->value,
            )),
            'movements' => $movements,
        ]);
    }

    public function update(SaveProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return back()->with('success', 'Producto actualizado.');
    }

    /** Desactivar / reactivar (no se borra: tiene historial). */
    public function toggle(Product $product): RedirectResponse
    {
        $product->active = ! $product->active;
        $product->save();

        return back()->with('success', $product->active ? 'Producto reactivado.' : 'Producto desactivado.');
    }

    private function present(Product $p): array
    {
        return [
            'id' => $p->id,
            'sku' => $p->sku,
            'name' => $p->name,
            'category' => $p->category->value,
            'categoryLabel' => $p->category->label(),
            'is_for_sale' => $p->is_for_sale,
            'price' => $p->price,
            'cost' => $p->cost,
            'stock' => $p->stock,
            'min_stock' => $p->min_stock,
            'active' => $p->active,
            'notes' => $p->notes,
            'lowStock' => $p->isLowStock(),
        ];
    }
}
