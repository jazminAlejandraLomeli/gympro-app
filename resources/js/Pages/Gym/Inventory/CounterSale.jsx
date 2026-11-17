import { router } from '@inertiajs/react';
import { Minus, Plus, Search, ShoppingCart, Trash2, UserRound, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import InventoryTabs from '@/Components/Inventory/InventoryTabs';
import { Button, Card, cx, EmptyState, Input, PageHeader, Stat } from '@/Components/ui';
import { getJson } from '@/lib/http';
import { formatDateTime, formatMoney, formatNumber } from '@/lib/format';

/** Venta rápida en mostrador: tocar productos, ajustar cantidades y cobrar. */
export default function CounterSale({ products, today }) {
    const [category, setCategory] = useState('');
    const [cart, setCart] = useState({}); // { [productId]: quantity }
    const [member, setMember] = useState(null);
    const [errors, setErrors] = useState({});
    const [processing, setProcessing] = useState(false);

    const byId = useMemo(() => Object.fromEntries(products.map((p) => [p.id, p])), [products]);
    const categories = useMemo(() => {
        const seen = new Map();
        products.forEach((p) => seen.set(p.category, p.categoryLabel));
        return [...seen.entries()];
    }, [products]);
    const visible = category ? products.filter((p) => p.category === category) : products;

    // Si el catálogo cambió (tras cobrar), recorta el carrito a lo que existe
    useEffect(() => {
        setCart((c) => Object.fromEntries(Object.entries(c).filter(([id, q]) => byId[id] && q > 0).map(([id, q]) => [id, Math.min(q, byId[id].stock)])));
    }, [byId]);

    const lines = Object.entries(cart)
        .filter(([id]) => byId[id])
        .map(([id, quantity]) => ({ product: byId[id], quantity }));
    const totalCents = lines.reduce((sum, l) => sum + Math.round(Number(l.product.price) * 100) * l.quantity, 0);
    const pieces = lines.reduce((sum, l) => sum + l.quantity, 0);

    const setQty = (id, qty) => {
        const p = byId[id];
        setErrors((e) => ({ ...e, [`product_${id}`]: undefined, items: undefined }));
        setCart((c) => {
            const next = { ...c };
            const value = Math.max(0, Math.min(qty, p.stock));
            if (value === 0) delete next[id];
            else next[id] = value;
            return next;
        });
    };

    const add = (p) => setQty(p.id, (cart[p.id] ?? 0) + 1);

    const charge = () => {
        if (!lines.length || processing) return;
        router.post(
            '/panel/inventario/ventas',
            {
                items: lines.map((l) => ({ product_id: l.product.id, quantity: l.quantity })),
                member_id: member?.id ?? null,
            },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    setCart({});
                    setMember(null);
                    setErrors({});
                },
                onError: (errs) => setErrors(errs),
            },
        );
    };

    const generalErrors = Object.entries(errors).filter(([k, v]) => v && !k.startsWith('product_'));

    return (
        <AppLayout title="Inventario">
            <PageHeader title="Inventario" description="Venta rápida en mostrador." />
            <InventoryTabs active="sale" />

            <div className="mb-5 grid grid-cols-2 gap-3">
                <Stat label="Vendido hoy" value={formatMoney(today.total)} icon="Wallet" />
                <Stat label="Piezas hoy" value={formatNumber(today.items)} icon="Package" />
            </div>

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_22rem]">
                {/* Catálogo */}
                <div className="min-w-0">
                    {categories.length > 1 && (
                        <div className="mb-3 flex flex-wrap gap-2">
                            {[['', 'Todo'], ...categories].map(([value, label]) => (
                                <button
                                    key={value || 'all'}
                                    type="button"
                                    onClick={() => setCategory(value)}
                                    className={cx(
                                        'rounded-full px-3.5 py-1.5 text-sm font-semibold',
                                        category === value ? 'bg-primary text-on-primary' : 'bg-white text-slate-600 ring-1 ring-slate-200',
                                    )}
                                >
                                    {label}
                                </button>
                            ))}
                        </div>
                    )}

                    {products.length === 0 ? (
                        <EmptyState
                            icon="Package"
                            title="No hay productos a la venta"
                            action={<Button href="/panel/inventario/productos/nuevo">Dar de alta un producto</Button>}
                        >
                            Da de alta suplementos, bebidas o accesorios para venderlos aquí.
                        </EmptyState>
                    ) : (
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                            {visible.map((p) => {
                                const inCart = cart[p.id] ?? 0;
                                const soldOut = p.stock <= 0;
                                const maxed = inCart >= p.stock;
                                return (
                                    <button
                                        key={p.id}
                                        type="button"
                                        onClick={() => add(p)}
                                        disabled={soldOut || maxed}
                                        className={cx(
                                            'relative flex min-h-28 flex-col justify-between rounded-2xl bg-white p-3 text-left shadow-sm ring-1 transition active:scale-[0.98] disabled:opacity-50',
                                            inCart ? 'ring-2 ring-primary' : 'ring-slate-200 hover:ring-slate-300',
                                        )}
                                    >
                                        {inCart > 0 && (
                                            <span className="absolute -top-2 -right-2 grid size-7 place-items-center rounded-full bg-primary text-sm font-bold text-on-primary">
                                                {inCart}
                                            </span>
                                        )}
                                        <span className="line-clamp-2 text-sm font-semibold break-words">{p.name}</span>
                                        <span className="mt-2">
                                            <span className="block text-lg font-bold">{formatMoney(p.price)}</span>
                                            <span className={cx('text-xs', p.lowStock ? 'font-semibold text-amber-700' : 'text-slate-500')}>
                                                {soldOut ? 'Agotado' : `Quedan ${p.stock}`}
                                            </span>
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    )}
                </div>

                {/* Carrito */}
                <div id="carrito" className="space-y-4 lg:sticky lg:top-6 lg:self-start">
                    <Card>
                        <h2 className="flex items-center gap-2 font-semibold">
                            <ShoppingCart className="size-5" aria-hidden="true" /> Carrito
                        </h2>

                        {lines.length === 0 ? (
                            <p className="mt-3 text-sm text-slate-500">Toca un producto para agregarlo.</p>
                        ) : (
                            <ul className="mt-3 divide-y divide-slate-100">
                                {lines.map(({ product: p, quantity }) => (
                                    <li key={p.id} className="py-2.5">
                                        <div className="flex items-start justify-between gap-2">
                                            <span className="min-w-0 text-sm font-medium break-words">{p.name}</span>
                                            <span className="shrink-0 text-sm font-semibold">{formatMoney(Number(p.price) * quantity)}</span>
                                        </div>
                                        <div className="mt-1.5 flex items-center gap-2">
                                            <QtyButton label={`Quitar uno de ${p.name}`} onClick={() => setQty(p.id, quantity - 1)}>
                                                <Minus className="size-4" aria-hidden="true" />
                                            </QtyButton>
                                            <span className="w-8 text-center font-semibold tabular-nums">{quantity}</span>
                                            <QtyButton label={`Agregar uno de ${p.name}`} onClick={() => setQty(p.id, quantity + 1)} disabled={quantity >= p.stock}>
                                                <Plus className="size-4" aria-hidden="true" />
                                            </QtyButton>
                                            <span className="text-xs text-slate-500">× {formatMoney(p.price)}</span>
                                            <button
                                                type="button"
                                                onClick={() => setQty(p.id, 0)}
                                                className="ml-auto rounded-lg p-1.5 text-slate-400 hover:text-red-600"
                                                aria-label={`Quitar ${p.name}`}
                                            >
                                                <Trash2 className="size-4" aria-hidden="true" />
                                            </button>
                                        </div>
                                        {errors[`product_${p.id}`] && <p className="mt-1 text-sm text-red-600">{errors[`product_${p.id}`]}</p>}
                                    </li>
                                ))}
                            </ul>
                        )}

                        <MemberPicker member={member} onChange={setMember} error={errors.member_id} />

                        {generalErrors.length > 0 && (
                            <div className="mt-3 rounded-xl bg-red-50 p-3 text-sm text-red-700" role="alert">
                                {generalErrors.map(([k, v]) => (
                                    <p key={k}>{v}</p>
                                ))}
                            </div>
                        )}

                        <div className="mt-4 flex items-end justify-between border-t border-slate-100 pt-4">
                            <span className="text-sm text-slate-500">
                                Total{pieces > 0 && ` · ${pieces} ${pieces === 1 ? 'pieza' : 'piezas'}`}
                            </span>
                            <span className="text-2xl font-bold">{formatMoney(totalCents / 100)}</span>
                        </div>
                        <Button size="lg" className="mt-3" onClick={charge} disabled={!lines.length || processing}>
                            {processing ? 'Cobrando…' : `Cobrar ${formatMoney(totalCents / 100)}`}
                        </Button>
                    </Card>

                    <Card>
                        <h2 className="font-semibold">Ventas de hoy</h2>
                        {today.recent.length === 0 ? (
                            <p className="mt-2 text-sm text-slate-500">Aún no hay ventas hoy.</p>
                        ) : (
                            <ul className="mt-2 divide-y divide-slate-100 text-sm">
                                {today.recent.map((s) => (
                                    <li key={s.id} className="flex items-start justify-between gap-2 py-2">
                                        <span className="min-w-0">
                                            <span className="block break-words">
                                                {s.quantity} × {s.product}
                                            </span>
                                            <span className="block text-xs text-slate-500">
                                                {formatDateTime(s.created_at)}
                                                {s.member && ` · ${s.member}`}
                                            </span>
                                        </span>
                                        <span className="shrink-0 font-semibold">{formatMoney(s.total)}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                </div>
            </div>

            {/* Barra inferior en móvil para llegar al carrito */}
            {lines.length > 0 && (
                <div className="fixed inset-x-0 bottom-0 z-10 border-t border-slate-200 bg-white p-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] lg:hidden">
                    <a href="#carrito" className="flex items-center justify-between rounded-2xl bg-primary px-4 py-3 font-semibold text-on-primary">
                        <span className="flex items-center gap-2">
                            <ShoppingCart className="size-5" aria-hidden="true" /> Ver carrito ({pieces})
                        </span>
                        <span>{formatMoney(totalCents / 100)}</span>
                    </a>
                </div>
            )}
            {lines.length > 0 && <div className="h-20 lg:hidden" aria-hidden="true" />}
        </AppLayout>
    );
}

function QtyButton({ label, children, ...props }) {
    return (
        <button
            type="button"
            aria-label={label}
            className="grid size-9 place-items-center rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 disabled:opacity-40"
            {...props}
        >
            {children}
        </button>
    );
}

/** Socio opcional: búsqueda por nombre o correo. */
function MemberPicker({ member, onChange, error }) {
    const [term, setTerm] = useState('');
    const [results, setResults] = useState([]);
    const [open, setOpen] = useState(false);
    const request = useRef(0);

    useEffect(() => {
        if (term.trim().length < 2) {
            setResults([]);
            return;
        }
        const id = ++request.current;
        const t = setTimeout(async () => {
            const { ok, data } = await getJson(`/panel/inventario/socios?q=${encodeURIComponent(term.trim())}`);
            if (id === request.current && ok) setResults(data.data ?? []);
        }, 250);
        return () => clearTimeout(t);
    }, [term]);

    if (member) {
        return (
            <div className="mt-4 flex items-center justify-between gap-2 rounded-xl bg-slate-50 px-3 py-2">
                <span className="flex min-w-0 items-center gap-2 text-sm">
                    <UserRound className="size-4 shrink-0 text-slate-500" aria-hidden="true" />
                    <span className="truncate font-medium">{member.name}</span>
                </span>
                <button type="button" onClick={() => onChange(null)} className="rounded-lg p-1 text-slate-400 hover:text-slate-700" aria-label="Quitar socio">
                    <X className="size-4" aria-hidden="true" />
                </button>
            </div>
        );
    }

    return (
        <div className="relative mt-4">
            <label htmlFor="member-search" className="mb-1.5 block text-sm font-medium text-slate-700">
                Socio <span className="font-normal text-slate-400">(opcional)</span>
            </label>
            <div className="relative">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                <Input
                    id="member-search"
                    value={term}
                    placeholder="Buscar por nombre o correo"
                    autoComplete="off"
                    className="pl-9"
                    onFocus={() => setOpen(true)}
                    onBlur={() => setTimeout(() => setOpen(false), 150)}
                    onChange={(e) => {
                        setTerm(e.target.value);
                        setOpen(true);
                    }}
                />
            </div>
            {open && results.length > 0 && (
                <ul className="absolute inset-x-0 z-10 mt-1 max-h-60 overflow-y-auto rounded-xl bg-white py-1 shadow-lg ring-1 ring-slate-200">
                    {results.map((m) => (
                        <li key={m.id}>
                            <button
                                type="button"
                                className="block w-full px-3 py-2 text-left text-sm hover:bg-slate-50"
                                onMouseDown={(e) => e.preventDefault()}
                                onClick={() => {
                                    onChange(m);
                                    setTerm('');
                                    setResults([]);
                                }}
                            >
                                <span className="block truncate font-medium">{m.name}</span>
                                <span className="block truncate text-xs text-slate-500">{m.email}</span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
            {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
            {!error && <p className="mt-1 text-xs text-slate-500">Si no eliges socio, se registra como venta al público.</p>}
        </div>
    );
}
