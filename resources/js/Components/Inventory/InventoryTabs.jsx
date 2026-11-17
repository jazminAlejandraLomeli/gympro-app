import { Link } from '@inertiajs/react';
import { History, Package, ShoppingCart } from 'lucide-react';
import { cx } from '@/Components/ui';

const TABS = [
    { key: 'sale', label: 'Venta', href: '/panel/inventario', icon: ShoppingCart },
    { key: 'products', label: 'Productos', href: '/panel/inventario/productos', icon: Package },
    { key: 'movements', label: 'Movimientos', href: '/panel/inventario/movimientos', icon: History },
];

/** Pestañas del módulo de inventario. */
export default function InventoryTabs({ active }) {
    return (
        <nav className="mb-5 grid grid-cols-3 gap-1 rounded-2xl bg-slate-100 p-1" aria-label="Secciones de inventario">
            {TABS.map(({ key, label, href, icon: TabIcon }) => (
                <Link
                    key={key}
                    href={href}
                    className={cx(
                        'flex items-center justify-center gap-2 rounded-xl px-2 py-2 text-sm font-semibold',
                        active === key ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-900',
                    )}
                    aria-current={active === key ? 'page' : undefined}
                >
                    <TabIcon className="size-4 shrink-0" aria-hidden="true" />
                    <span className="truncate">{label}</span>
                </Link>
            ))}
        </nav>
    );
}
