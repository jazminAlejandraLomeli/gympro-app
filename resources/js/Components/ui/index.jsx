/**
 * Kit de UI compartido. Usa SIEMPRE estos componentes para que todos los
 * módulos se vean igual y respeten la marca blanca (bg-primary, etc.).
 *
 *   import { Button, Card, Field, Input, PageHeader } from '@/Components/ui';
 */
import { Link } from '@inertiajs/react';
import {
    Activity, Bug, Building2, Circle, Clapperboard, ClipboardList, Dumbbell, House, Inbox, LayoutDashboard,
    LogOut, Megaphone, Menu, Package, Palette, QrCode, Ruler, ScanLine, TrendingUp, UserCog, Users, Wallet, X,
} from 'lucide-react';

export const cx = (...c) => c.filter(Boolean).join(' ');

/*
 * Íconos que el servidor puede pedir por nombre (menú, EmptyState, Stat).
 * Dentro de tus propios componentes importa directo de 'lucide-react'.
 */
const ICONS = {
    Activity, Bug, Building2, Circle, Clapperboard, ClipboardList, Dumbbell, House, Inbox, LayoutDashboard,
    LogOut, Megaphone, Menu, Package, Palette, QrCode, Ruler, ScanLine, TrendingUp, UserCog, Users, Wallet, X,
};

/** Ícono por nombre: <Icon name="Users" />. Para otros, importa de lucide-react. */
export function Icon({ name, as, className = 'size-5', ...props }) {
    const Cmp = as ?? ICONS[name] ?? Circle;
    return <Cmp className={className} aria-hidden="true" {...props} />;
}

const BUTTON_VARIANTS = {
    primary: 'bg-primary text-on-primary hover:opacity-90',
    secondary: 'bg-white text-slate-900 ring-1 ring-slate-300 hover:bg-slate-50',
    danger: 'bg-red-600 text-white hover:bg-red-700',
    ghost: 'text-slate-700 hover:bg-slate-100',
};
const BUTTON_SIZES = {
    sm: 'px-3 py-1.5 text-sm rounded-lg',
    md: 'px-4 py-2.5 text-sm rounded-xl',
    lg: 'px-6 py-4 text-lg rounded-2xl w-full', // acciones principales en móvil
};

/** Con `href` se vuelve Link de Inertia. */
export function Button({ variant = 'primary', size = 'md', href, className, children, ...props }) {
    const classes = cx(
        'inline-flex items-center justify-center gap-2 font-semibold transition active:scale-[0.98] disabled:opacity-50 disabled:pointer-events-none',
        BUTTON_VARIANTS[variant],
        BUTTON_SIZES[size],
        className,
    );
    if (href) {
        return (
            <Link href={href} className={classes} {...props}>
                {children}
            </Link>
        );
    }
    return (
        <button type="button" className={classes} {...props}>
            {children}
        </button>
    );
}

export function Card({ className, children, ...props }) {
    return (
        <section className={cx('rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200/60 sm:p-5', className)} {...props}>
            {children}
        </section>
    );
}

export function PageHeader({ title, description, actions }) {
    return (
        <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h1 className="text-2xl font-bold tracking-tight">{title}</h1>
                {description && <p className="mt-1 text-sm text-slate-500">{description}</p>}
            </div>
            {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
        </div>
    );
}

export function Field({ label, error, hint, htmlFor, children, className }) {
    return (
        <div className={cx('space-y-1.5', className)}>
            {label && (
                <label htmlFor={htmlFor} className="block text-sm font-medium text-slate-700">
                    {label}
                </label>
            )}
            {children}
            {hint && !error && <p className="text-xs text-slate-500">{hint}</p>}
            {error && <p className="text-sm text-red-600">{error}</p>}
        </div>
    );
}

const INPUT = 'block w-full rounded-xl border-0 bg-white px-3.5 py-2.5 text-base text-slate-900 ring-1 ring-slate-300 placeholder:text-slate-400 focus:ring-2 focus:ring-primary sm:text-sm';

export const Input = ({ className, ...props }) => <input className={cx(INPUT, className)} {...props} />;
export const Textarea = ({ className, ...props }) => <textarea className={cx(INPUT, 'min-h-24', className)} {...props} />;
export const Select = ({ className, children, ...props }) => (
    <select className={cx(INPUT, className)} {...props}>
        {children}
    </select>
);

const BADGE_TONES = {
    green: 'bg-green-100 text-green-800',
    red: 'bg-red-100 text-red-800',
    amber: 'bg-amber-100 text-amber-800',
    slate: 'bg-slate-100 text-slate-700',
    primary: 'bg-primary text-on-primary',
};

export function Badge({ tone = 'slate', children }) {
    return <span className={cx('inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold', BADGE_TONES[tone])}>{children}</span>;
}

export function EmptyState({ icon = 'Inbox', title, children, action }) {
    return (
        <div className="flex flex-col items-center rounded-2xl border-2 border-dashed border-slate-200 px-6 py-12 text-center">
            <Icon name={icon} className="size-10 text-slate-300" />
            <h3 className="mt-3 font-semibold">{title}</h3>
            {children && <p className="mt-1 max-w-sm text-sm text-slate-500">{children}</p>}
            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}

/** Tarjeta de cifra para tableros. */
export function Stat({ label, value, icon, hint }) {
    return (
        <Card className="flex items-start gap-3">
            {icon && (
                <div className="grid size-10 shrink-0 place-items-center rounded-xl bg-primary text-on-primary">
                    <Icon name={icon} />
                </div>
            )}
            <div className="min-w-0">
                <p className="text-sm text-slate-500">{label}</p>
                <p className="truncate text-2xl font-bold">{value}</p>
                {hint && <p className="text-xs text-slate-500">{hint}</p>}
            </div>
        </Card>
    );
}

/** Paginación con el arreglo `links` del paginador de Laravel. */
export function Pagination({ links }) {
    if (!links || links.length <= 3) return null;
    return (
        <nav className="mt-4 flex flex-wrap justify-center gap-1" aria-label="Paginación">
            {links.map((l, i) =>
                l.url ? (
                    <Link
                        key={i}
                        href={l.url}
                        preserveScroll
                        className={cx('rounded-lg px-3 py-1.5 text-sm', l.active ? 'bg-primary text-on-primary' : 'hover:bg-slate-100')}
                        dangerouslySetInnerHTML={{ __html: l.label }}
                    />
                ) : (
                    <span key={i} className="px-3 py-1.5 text-sm text-slate-400" dangerouslySetInnerHTML={{ __html: l.label }} />
                ),
            )}
        </nav>
    );
}

/**
 * Tabla responsiva: en móvil hace scroll horizontal dentro de la tarjeta
 * (nunca de la página completa).
 */
export function Table({ children }) {
    return (
        <div className="-mx-4 overflow-x-auto sm:mx-0">
            <table className="min-w-full divide-y divide-slate-200 text-sm">{children}</table>
        </div>
    );
}
export const Th = ({ className, ...p }) => <th className={cx('px-4 py-2.5 text-left font-semibold whitespace-nowrap text-slate-600', className)} {...p} />;
export const Td = ({ className, ...p }) => <td className={cx('px-4 py-3 whitespace-nowrap', className)} {...p} />;
