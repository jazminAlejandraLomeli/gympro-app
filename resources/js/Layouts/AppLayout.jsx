import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { cx, Icon } from '@/Components/ui';

/**
 * Layout de todas las pantallas autenticadas.
 *  - Socio (member): encabezado con la marca + barra inferior de pestañas
 *    (pensado para usarse con una mano dentro del gimnasio).
 *  - Personal y super_admin: barra lateral en escritorio, menú desplegable en móvil.
 *
 *   export default function MiPagina() {
 *       return <AppLayout title="Socios">...</AppLayout>;
 *   }
 */
export default function AppLayout({ title, children }) {
    const { auth } = usePage().props;
    const isMember = auth.user?.role === 'member';

    return (
        <>
            <Head title={title} />
            {isMember ? <MemberShell>{children}</MemberShell> : <StaffShell>{children}</StaffShell>}
            <Flash />
        </>
    );
}

function BrandMark({ className }) {
    const { brand } = usePage().props;
    return (
        <div className={cx('flex min-w-0 items-center gap-3', className)}>
            {brand.logoUrl ? (
                <img src={brand.logoUrl} alt="" className="size-9 shrink-0 rounded-full bg-white object-cover" />
            ) : (
                <div className="grid size-9 shrink-0 place-items-center rounded-full bg-primary font-bold text-on-primary">
                    {brand.name.charAt(0)}
                </div>
            )}
            <span className="truncate text-lg font-semibold">{brand.name}</span>
        </div>
    );
}

function LogoutButton({ className }) {
    return (
        <Link href="/logout" method="post" as="button" className={cx('flex items-center gap-2', className)}>
            <Icon name="LogOut" /> Salir
        </Link>
    );
}

function MemberShell({ children }) {
    const { nav } = usePage().props;

    return (
        <div className="min-h-dvh pb-[calc(5rem+env(safe-area-inset-bottom))]">
            <header className="sticky top-0 z-20 bg-secondary text-on-secondary">
                <div className="mx-auto flex max-w-md items-center justify-between gap-3 px-4 py-3">
                    <BrandMark />
                    <LogoutButton className="rounded-lg px-2 py-1 text-sm opacity-90" />
                </div>
            </header>

            <main className="mx-auto max-w-md space-y-4 px-4 py-5">{children}</main>

            <nav
                className="fixed inset-x-0 bottom-0 z-20 border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)]"
                aria-label="Navegación principal"
            >
                <ul className="mx-auto grid max-w-md" style={{ gridTemplateColumns: `repeat(${nav.length || 1}, minmax(0, 1fr))` }}>
                    {nav.map((item) => (
                        <li key={item.href}>
                            <Link
                                href={item.href}
                                className={cx(
                                    'flex flex-col items-center gap-1 py-2.5 text-xs font-medium',
                                    item.active ? 'text-slate-900' : 'text-slate-400',
                                )}
                                aria-current={item.active ? 'page' : undefined}
                            >
                                <span className={cx('grid h-8 w-12 place-items-center rounded-full', item.active && 'bg-primary text-on-primary')}>
                                    <Icon name={item.icon} />
                                </span>
                                {item.label}
                            </Link>
                        </li>
                    ))}
                </ul>
            </nav>
        </div>
    );
}

function StaffNav({ onNavigate }) {
    const { nav } = usePage().props;
    return (
        <ul className="space-y-1">
            {nav.map((item) => (
                <li key={item.href}>
                    <Link
                        href={item.href}
                        onClick={onNavigate}
                        className={cx(
                            'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium',
                            item.active ? 'bg-primary text-on-primary' : 'text-on-secondary/80 hover:bg-white/10',
                        )}
                        aria-current={item.active ? 'page' : undefined}
                    >
                        <Icon name={item.icon} />
                        {item.label}
                    </Link>
                </li>
            ))}
        </ul>
    );
}

function UserBox() {
    const { auth } = usePage().props;
    return (
        <div className="border-t border-white/10 pt-4">
            <p className="truncate text-sm font-semibold">{auth.user.name}</p>
            <p className="truncate text-xs opacity-70">{auth.user.roleLabel}</p>
            <LogoutButton className="mt-3 text-sm opacity-80 hover:opacity-100" />
        </div>
    );
}

function StaffShell({ children }) {
    const [open, setOpen] = useState(false);
    const { url } = usePage();

    useEffect(() => setOpen(false), [url]);

    return (
        <div className="min-h-dvh lg:flex">
            {/* Escritorio */}
            <aside className="hidden w-64 shrink-0 flex-col gap-6 bg-secondary p-4 text-on-secondary lg:sticky lg:top-0 lg:flex lg:h-dvh">
                <BrandMark />
                <nav className="flex-1 overflow-y-auto" aria-label="Navegación principal">
                    <StaffNav />
                </nav>
                <UserBox />
            </aside>

            {/* Móvil */}
            <header className="sticky top-0 z-20 flex items-center justify-between bg-secondary px-4 py-3 text-on-secondary lg:hidden">
                <BrandMark />
                <button type="button" onClick={() => setOpen(true)} className="rounded-lg p-2" aria-label="Abrir menú">
                    <Icon name="Menu" />
                </button>
            </header>
            {open && (
                <div className="fixed inset-0 z-30 lg:hidden" role="dialog" aria-modal="true">
                    <div className="absolute inset-0 bg-black/40" onClick={() => setOpen(false)} />
                    <div className="absolute inset-y-0 left-0 flex w-72 max-w-[85%] flex-col gap-6 bg-secondary p-4 text-on-secondary">
                        <div className="flex items-center justify-between gap-2">
                            <BrandMark />
                            <button type="button" onClick={() => setOpen(false)} className="rounded-lg p-2" aria-label="Cerrar menú">
                                <Icon name="X" />
                            </button>
                        </div>
                        <nav className="flex-1 overflow-y-auto">
                            <StaffNav onNavigate={() => setOpen(false)} />
                        </nav>
                        <UserBox />
                    </div>
                </div>
            )}

            <main className="mx-auto w-full max-w-6xl min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-8">{children}</main>
        </div>
    );
}

/** Mensajes flash: back()->with('success', '...') o ->with('error', '...') */
function Flash() {
    const { flash } = usePage().props;
    const [visible, setVisible] = useState(null);

    useEffect(() => {
        const message = flash?.success ? { tone: 'success', text: flash.success } : flash?.error ? { tone: 'error', text: flash.error } : null;
        setVisible(message);
        if (!message) return;
        const t = setTimeout(() => setVisible(null), 4000);
        return () => clearTimeout(t);
    }, [flash]);

    if (!visible) return null;

    return (
        <div className="pointer-events-none fixed inset-x-0 top-3 z-50 flex justify-center px-4" role="status">
            <div
                className={cx(
                    'pointer-events-auto rounded-xl px-4 py-3 text-sm font-medium shadow-lg',
                    visible.tone === 'success' ? 'bg-slate-900 text-white' : 'bg-red-600 text-white',
                )}
            >
                {visible.text}
            </div>
        </div>
    );
}
