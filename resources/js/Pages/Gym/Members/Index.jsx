import { Link, router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { ChevronRight, Search, UserPlus } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, Card, EmptyState, Input, PageHeader, Pagination, cx } from '@/Components/ui';
import StatusBadge from '@/Components/Membership/StatusBadge';
import { validitySummary } from '@/Components/Membership/validity';

const TABS = [
    { value: null, label: 'Todos', count: 'all' },
    { value: 'active', label: 'Activos', count: 'active' },
    { value: 'expiring', label: 'Por vencer', count: 'expiring' },
    { value: 'expired', label: 'Vencidos', count: 'expired' },
];

/** Socios: búsqueda instantánea (Meilisearch) + filtro por estado. */
export default function Index({ members, filters, counts }) {
    const [q, setQ] = useState(filters.q ?? '');
    const first = useRef(true);

    const visit = (params) =>
        router.get('/panel/socios', params, { preserveState: true, preserveScroll: true, replace: true, only: ['members', 'filters', 'counts'] });

    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const t = setTimeout(() => visit({ q: q || undefined, estado: filters.estado || undefined }), 250);
        return () => clearTimeout(t);
    }, [q]);

    return (
        <AppLayout title="Socios">
            <PageHeader
                title="Socios"
                description="Busca por nombre, correo o teléfono."
                actions={
                    <Button href="/panel/socios/nuevo">
                        <UserPlus className="size-5" aria-hidden="true" /> Nuevo socio
                    </Button>
                }
            />

            <div className="relative mb-3">
                <Search className="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                <Input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar socio…" className="py-3 pl-11" aria-label="Buscar socio" autoFocus />
            </div>

            <div className="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:px-0">
                {TABS.map((tab) => {
                    const active = (filters.estado ?? null) === tab.value;
                    return (
                        <button
                            key={tab.label}
                            type="button"
                            onClick={() => visit({ q: q || undefined, estado: tab.value || undefined })}
                            className={cx(
                                'shrink-0 rounded-full px-4 py-2 text-sm font-semibold ring-1',
                                active ? 'bg-primary text-on-primary ring-transparent' : 'bg-white text-slate-700 ring-slate-300',
                            )}
                        >
                            {tab.label} <span className="opacity-70">{counts[tab.count]}</span>
                        </button>
                    );
                })}
            </div>

            {members.data.length === 0 ? (
                <EmptyState icon="Users" title={q ? 'Sin resultados' : 'Aún no hay socios'}>
                    {q ? 'Prueba con otro nombre, correo o teléfono.' : 'Registra a tu primer socio para generar su credencial QR.'}
                </EmptyState>
            ) : (
                <Card className="p-0 sm:p-0">
                    <ul className="divide-y divide-slate-100">
                        {members.data.map((m) => (
                            <li key={m.id}>
                                <Link href={`/panel/socios/${m.id}`} className="flex items-center gap-3 px-4 py-3.5 hover:bg-slate-50">
                                    <span className="grid size-10 shrink-0 place-items-center rounded-full bg-secondary font-bold text-on-secondary">
                                        {m.name?.charAt(0).toUpperCase()}
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <p className="truncate font-semibold">{m.name}</p>
                                            <StatusBadge status={m.status} label={m.status_label} />
                                        </div>
                                        <p className="truncate text-sm text-slate-500">{validitySummary(m)}</p>
                                        <p className="truncate text-xs text-slate-400">{[m.phone, m.email].filter(Boolean).join(' · ')}</p>
                                    </div>
                                    <ChevronRight className="size-5 shrink-0 text-slate-300" aria-hidden="true" />
                                </Link>
                            </li>
                        ))}
                    </ul>
                </Card>
            )}

            <Pagination links={members.links} />
        </AppLayout>
    );
}
