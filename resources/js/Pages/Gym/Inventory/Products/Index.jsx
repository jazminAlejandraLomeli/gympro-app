import { Link, router } from '@inertiajs/react';
import { AlertTriangle, Pencil, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import InventoryTabs from '@/Components/Inventory/InventoryTabs';
import StockBadge from '@/Components/Inventory/StockBadge';
import { Badge, Button, Card, cx, EmptyState, Input, PageHeader, Pagination, Select } from '@/Components/ui';
import { formatMoney } from '@/lib/format';

/** Catálogo con filtros por categoría y stock bajo. */
export default function Index({ products, filters, categories, lowStockCount }) {
    const [q, setQ] = useState(filters.q ?? '');

    const apply = (changes) => {
        const params = { ...filters, q, ...changes };
        Object.keys(params).forEach((k) => (params[k] === '' || params[k] === null || params[k] === false) && delete params[k]);
        Object.keys(params).forEach((k) => params[k] === true && (params[k] = 1));
        router.get('/panel/inventario/productos', params, { preserveState: true, preserveScroll: true, replace: true });
    };

    return (
        <AppLayout title="Productos">
            <PageHeader
                title="Inventario"
                description="Productos a la venta y equipo del gimnasio."
                actions={
                    <Button href="/panel/inventario/productos/nuevo">
                        <Plus className="size-4" aria-hidden="true" /> Nuevo producto
                    </Button>
                }
            />
            <InventoryTabs active="products" />

            {lowStockCount > 0 && !filters.low && (
                <button
                    type="button"
                    onClick={() => apply({ low: true })}
                    className="mb-4 flex w-full items-center gap-2 rounded-xl bg-amber-50 px-4 py-3 text-left text-sm text-amber-800 ring-1 ring-amber-200"
                >
                    <AlertTriangle className="size-4 shrink-0" aria-hidden="true" />
                    {lowStockCount === 1 ? '1 producto tiene' : `${lowStockCount} productos tienen`} stock bajo. Ver solo esos.
                </button>
            )}

            <Card className="mb-4">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        apply({});
                    }}
                    className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_12rem]"
                >
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                        <Input
                            type="search"
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Buscar por nombre o SKU"
                            className="pl-9"
                            aria-label="Buscar productos"
                        />
                    </div>
                    <Select value={filters.category ?? ''} onChange={(e) => apply({ category: e.target.value })} aria-label="Categoría">
                        <option value="">Todas las categorías</option>
                        {categories.map((c) => (
                            <option key={c.value} value={c.value}>
                                {c.label}
                            </option>
                        ))}
                    </Select>
                </form>
                <div className="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-sm text-slate-700">
                    <label className="flex items-center gap-2">
                        <input type="checkbox" checked={filters.low} onChange={(e) => apply({ low: e.target.checked })} />
                        Solo stock bajo
                    </label>
                    <label className="flex items-center gap-2">
                        <input type="checkbox" checked={filters.inactive} onChange={(e) => apply({ inactive: e.target.checked })} />
                        Incluir desactivados
                    </label>
                </div>
            </Card>

            {products.data.length === 0 ? (
                <EmptyState icon="Package" title="Sin productos" action={<Button href="/panel/inventario/productos/nuevo">Dar de alta</Button>}>
                    No hay productos con estos filtros.
                </EmptyState>
            ) : (
                <section className="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/60">
                    <ul className="divide-y divide-slate-100">
                        {products.data.map((p) => (
                            <li key={p.id} className={cx('flex items-center gap-3 px-4 py-3', !p.active && 'opacity-60')}>
                                <div className="min-w-0 flex-1">
                                    <Link href={`/panel/inventario/productos/${p.id}/editar`} className="font-semibold break-words hover:underline">
                                        {p.name}
                                    </Link>
                                    <div className="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
                                        <Badge>{p.categoryLabel}</Badge>
                                        <StockBadge stock={p.stock} lowStock={p.lowStock} />
                                        {!p.active && <Badge tone="red">Desactivado</Badge>}
                                        {p.sku && <span className="font-mono">{p.sku}</span>}
                                    </div>
                                </div>
                                <div className="shrink-0 text-right">
                                    <p className={cx('text-lg font-bold tabular-nums', p.lowStock && 'text-amber-700')}>{p.stock}</p>
                                    <p className="text-xs text-slate-500">{p.is_for_sale ? formatMoney(p.price) : 'No se vende'}</p>
                                </div>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    href={`/panel/inventario/productos/${p.id}/editar`}
                                    aria-label={`Editar ${p.name}`}
                                    className="shrink-0"
                                >
                                    <Pencil className="size-4" aria-hidden="true" />
                                </Button>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            <Pagination links={products.links} />
        </AppLayout>
    );
}
