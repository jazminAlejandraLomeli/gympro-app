import { router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import InventoryTabs from '@/Components/Inventory/InventoryTabs';
import MovementList from '@/Components/Inventory/MovementList';
import { Button, Card, Field, Input, PageHeader, Pagination, Select } from '@/Components/ui';

/** Historial de movimientos de existencias, paginado y filtrable. */
export default function Movements({ movements, filters, types, products }) {
    const apply = (changes) => {
        const params = { ...filters, ...changes };
        Object.keys(params).forEach((k) => params[k] === '' && delete params[k]);
        router.get('/panel/inventario/movimientos', params, { preserveState: true, preserveScroll: true, replace: true });
    };
    const hasFilters = Object.values(filters).some((v) => v !== '');

    return (
        <AppLayout title="Movimientos">
            <PageHeader title="Inventario" description="Entradas, salidas, ajustes y ventas." />
            <InventoryTabs active="movements" />

            <Card className="mb-4">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <Field label="Producto" htmlFor="f-product">
                        <Select id="f-product" value={filters.product} onChange={(e) => apply({ product: e.target.value })}>
                            <option value="">Todos</option>
                            {products.map((p) => (
                                <option key={p.id} value={p.id}>
                                    {p.name}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Tipo" htmlFor="f-type">
                        <Select id="f-type" value={filters.type} onChange={(e) => apply({ type: e.target.value })}>
                            <option value="">Todos</option>
                            {types.map((t) => (
                                <option key={t.value} value={t.value}>
                                    {t.label}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="Desde" htmlFor="f-from">
                        <Input id="f-from" type="date" value={filters.from} onChange={(e) => apply({ from: e.target.value })} />
                    </Field>
                    <Field label="Hasta" htmlFor="f-to">
                        <Input id="f-to" type="date" value={filters.to} onChange={(e) => apply({ to: e.target.value })} />
                    </Field>
                </div>
                {hasFilters && (
                    <Button variant="ghost" size="sm" className="mt-2" href="/panel/inventario/movimientos">
                        Quitar filtros
                    </Button>
                )}
            </Card>

            <Card>
                <MovementList movements={movements.data} />
            </Card>
            <Pagination links={movements.links} />
        </AppLayout>
    );
}
