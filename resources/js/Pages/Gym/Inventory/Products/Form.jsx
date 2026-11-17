import { Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import InventoryTabs from '@/Components/Inventory/InventoryTabs';
import MovementList from '@/Components/Inventory/MovementList';
import StockBadge from '@/Components/Inventory/StockBadge';
import { Button, Card, Field, Input, PageHeader, Select, Textarea } from '@/Components/ui';

/** Alta y edición de producto; al editar, registro de existencias y últimos movimientos. */
export default function Form({ product, categories, movementTypes, movements }) {
    const editing = Boolean(product);
    const form = useForm({
        sku: product?.sku ?? '',
        name: product?.name ?? '',
        category: product?.category ?? 'suplemento',
        is_for_sale: product?.is_for_sale ?? true,
        price: product?.price ?? '',
        cost: product?.cost ?? '',
        min_stock: product?.min_stock ?? 0,
        active: product?.active ?? true,
        notes: product?.notes ?? '',
        initial_stock: 0,
    });
    const isEquipment = form.data.category === 'equipo';

    const submit = (e) => {
        e.preventDefault();
        if (editing) form.put(`/panel/inventario/productos/${product.id}`, { preserveScroll: true });
        else form.post('/panel/inventario/productos');
    };

    const toggle = () => {
        const verb = product.active ? 'desactivar' : 'reactivar';
        if (!confirm(`¿Seguro que quieres ${verb} «${product.name}»?`)) return;
        router.patch(`/panel/inventario/productos/${product.id}/activo`, {}, { preserveScroll: true });
    };

    return (
        <AppLayout title={editing ? product.name : 'Nuevo producto'}>
            <PageHeader title="Inventario" />
            <InventoryTabs active="products" />

            <Link href="/panel/inventario/productos" className="mb-3 inline-flex items-center gap-1 text-sm text-slate-500 hover:text-slate-900">
                <ArrowLeft className="size-4" aria-hidden="true" /> Productos
            </Link>

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,24rem)]">
                <Card>
                    <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <h1 className="text-xl font-bold break-words">{editing ? product.name : 'Nuevo producto'}</h1>
                        {editing && <StockBadge stock={product.stock} lowStock={product.lowStock} />}
                    </div>

                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Nombre" htmlFor="name" error={form.errors.name} className="sm:col-span-2">
                                <Input id="name" value={form.data.name} maxLength={120} onChange={(e) => form.setData('name', e.target.value)} />
                            </Field>
                            <Field label="Categoría" htmlFor="category" error={form.errors.category}>
                                <Select id="category" value={form.data.category} onChange={(e) => form.setData('category', e.target.value)}>
                                    {categories.map((c) => (
                                        <option key={c.value} value={c.value}>
                                            {c.label}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            <Field label="SKU" htmlFor="sku" error={form.errors.sku} hint="Opcional. Único en tu gimnasio.">
                                <Input id="sku" value={form.data.sku} maxLength={40} className="font-mono uppercase" onChange={(e) => form.setData('sku', e.target.value)} />
                            </Field>
                        </div>

                        {isEquipment ? (
                            <p className="rounded-xl bg-slate-50 p-3 text-sm text-slate-600">
                                El equipo del gimnasio se controla en inventario pero no aparece en la venta de mostrador.
                            </p>
                        ) : (
                            <label className="flex items-center gap-2 text-sm text-slate-700">
                                <input type="checkbox" checked={form.data.is_for_sale} onChange={(e) => form.setData('is_for_sale', e.target.checked)} />
                                Se vende en mostrador
                            </label>
                        )}

                        <div className="grid gap-4 sm:grid-cols-3">
                            {!isEquipment && (
                                <Field label="Precio de venta" htmlFor="price" error={form.errors.price}>
                                    <Input id="price" type="number" inputMode="decimal" min="0" step="0.01" value={form.data.price} onChange={(e) => form.setData('price', e.target.value)} />
                                </Field>
                            )}
                            <Field label={isEquipment ? 'Costo unitario' : 'Costo'} htmlFor="cost" error={form.errors.cost} hint="Opcional.">
                                <Input id="cost" type="number" inputMode="decimal" min="0" step="0.01" value={form.data.cost ?? ''} onChange={(e) => form.setData('cost', e.target.value)} />
                            </Field>
                            <Field label="Stock mínimo" htmlFor="min_stock" error={form.errors.min_stock} hint="Avisa cuando llegue a este número.">
                                <Input id="min_stock" type="number" inputMode="numeric" min="0" step="1" value={form.data.min_stock} onChange={(e) => form.setData('min_stock', e.target.value)} />
                            </Field>
                            {!editing && (
                                <Field label="Existencia inicial" htmlFor="initial_stock" error={form.errors.initial_stock}>
                                    <Input
                                        id="initial_stock"
                                        type="number"
                                        inputMode="numeric"
                                        min="0"
                                        step="1"
                                        value={form.data.initial_stock}
                                        onChange={(e) => form.setData('initial_stock', e.target.value)}
                                    />
                                </Field>
                            )}
                        </div>

                        <Field label="Notas" htmlFor="notes" error={form.errors.notes}>
                            <Textarea id="notes" rows={2} maxLength={1000} value={form.data.notes ?? ''} onChange={(e) => form.setData('notes', e.target.value)} />
                        </Field>

                        <div className="flex flex-wrap gap-2">
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Guardando…' : editing ? 'Guardar cambios' : 'Dar de alta'}
                            </Button>
                            {editing && (
                                <Button variant={product.active ? 'secondary' : 'primary'} onClick={toggle}>
                                    {product.active ? 'Desactivar' : 'Reactivar'}
                                </Button>
                            )}
                        </div>
                    </form>
                </Card>

                {editing && (
                    <div className="space-y-4">
                        <StockMovementForm product={product} types={movementTypes} />
                        <Card>
                            <div className="mb-2 flex items-center justify-between gap-2">
                                <h2 className="font-semibold">Últimos movimientos</h2>
                                <Link href={`/panel/inventario/movimientos?product=${product.id}`} className="text-sm font-medium text-slate-500 hover:text-slate-900">
                                    Ver todo
                                </Link>
                            </div>
                            <MovementList movements={movements} showProduct={false} />
                        </Card>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

const QTY_LABELS = {
    entrada: 'Cantidad que entra',
    salida: 'Cantidad que sale',
    ajuste: 'Existencia real contada',
};

function StockMovementForm({ product, types }) {
    const form = useForm({ type: 'entrada', quantity: '', note: '' });

    const submit = (e) => {
        e.preventDefault();
        form.post(`/panel/inventario/productos/${product.id}/movimientos`, {
            preserveScroll: true,
            onSuccess: () => form.reset('quantity', 'note'),
        });
    };

    return (
        <Card>
            <div className="mb-3 flex items-baseline justify-between gap-2">
                <h2 className="font-semibold">Existencias</h2>
                <p className="text-sm text-slate-500">
                    Actual: <span className="text-lg font-bold text-slate-900 tabular-nums">{product.stock}</span>
                </p>
            </div>
            <form onSubmit={submit} className="space-y-3">
                <div className="grid grid-cols-3 gap-1 rounded-xl bg-slate-100 p-1" role="radiogroup" aria-label="Tipo de movimiento">
                    {types.map((t) => (
                        <button
                            key={t.value}
                            type="button"
                            role="radio"
                            aria-checked={form.data.type === t.value}
                            onClick={() => form.setData('type', t.value)}
                            className={
                                form.data.type === t.value
                                    ? 'rounded-lg bg-white py-1.5 text-sm font-semibold shadow-sm'
                                    : 'rounded-lg py-1.5 text-sm text-slate-500'
                            }
                        >
                            {t.label}
                        </button>
                    ))}
                </div>
                {form.errors.type && <p className="text-sm text-red-600">{form.errors.type}</p>}
                <Field label={QTY_LABELS[form.data.type]} htmlFor="mv-quantity" error={form.errors.quantity}>
                    <Input
                        id="mv-quantity"
                        type="number"
                        inputMode="numeric"
                        min={form.data.type === 'ajuste' ? 0 : 1}
                        step="1"
                        value={form.data.quantity}
                        onChange={(e) => form.setData('quantity', e.target.value)}
                    />
                </Field>
                <Field
                    label={form.data.type === 'entrada' ? 'Nota (opcional)' : 'Motivo'}
                    htmlFor="mv-note"
                    error={form.errors.note}
                >
                    <Input
                        id="mv-note"
                        value={form.data.note}
                        maxLength={255}
                        placeholder={form.data.type === 'entrada' ? 'Proveedor, factura…' : 'Merma, caducidad, conteo físico…'}
                        onChange={(e) => form.setData('note', e.target.value)}
                    />
                </Field>
                <Button type="submit" className="w-full" disabled={form.processing}>
                    {form.processing ? 'Registrando…' : 'Registrar'}
                </Button>
            </form>
        </Card>
    );
}
