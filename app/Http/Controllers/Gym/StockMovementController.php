<?php

namespace App\Http\Controllers\Gym;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreStockMovementRequest;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Inventory\AdjustStock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Historial de movimientos y registro de entradas / salidas / ajustes. */
class StockMovementController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'product' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::enum(StockMovementType::class)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $movements = StockMovement::query()
            ->with(['product:id,name', 'user:id,name', 'member:id,name'])
            ->when($filters['product'] ?? null, fn ($q, $id) => $q->where('product_id', $id))
            ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', Carbon::parse($d)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', Carbon::parse($d)->endOfDay()))
            ->latest('created_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (StockMovement $m) => self::present($m));

        return Inertia::render('Gym/Inventory/Movements', [
            'movements' => $movements,
            'filters' => [
                'product' => isset($filters['product']) ? (string) $filters['product'] : '',
                'type' => $filters['type'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
            ],
            'types' => StockMovementType::options(),
            'products' => Product::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreStockMovementRequest $request, Product $product, AdjustStock $adjust): RedirectResponse
    {
        $type = $request->movementType();

        $adjust->handle($request->user(), $product, $type, (int) $request->validated('quantity'), $request->validated('note'));

        return back()->with('success', "{$type->label()} registrada. Existencia actual: {$product->stock}.");
    }

    public static function present(StockMovement $m): array
    {
        return [
            'id' => $m->id,
            'type' => $m->type->value,
            'typeLabel' => $m->type->label(),
            'quantity' => $m->quantity,
            'unit_price' => $m->unit_price,
            'total' => $m->total,
            'note' => $m->note,
            'product' => $m->product?->name,
            'product_id' => $m->product_id,
            'user' => $m->user?->name,
            'member' => $m->member?->name,
            'created_at' => $m->created_at?->toIso8601String(),
        ];
    }
}
