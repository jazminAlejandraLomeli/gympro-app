import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ChevronRight, Search } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, Button, Card, EmptyState, Input, PageHeader, Pagination, cx } from '@/Components/ui';
import { formatDate } from '@/lib/format';

/** Socios con su última valoración y marca de pendiente (30+ días). */
export default function Index({ members, filters }) {
    const [q, setQ] = useState(filters.q ?? '');

    const visit = (params) =>
        router.get('/panel/valoraciones', { q: q || undefined, pendientes: filters.pendientes ? 1 : undefined, ...params }, { preserveState: true, replace: true });

    const submit = (e) => {
        e.preventDefault();
        visit({});
    };

    return (
        <AppLayout title="Valoraciones">
            <PageHeader title="Valoraciones" description="Evolución física de tus socios. Se recomienda una valoración cada 30 días." />

            <Card className="mb-4 space-y-3">
                <form onSubmit={submit} className="flex gap-2" role="search">
                    <div className="relative min-w-0 flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                        <Input type="search" aria-label="Buscar socio por nombre" placeholder="Buscar por nombre" value={q} onChange={(e) => setQ(e.target.value)} className="pl-9" />
                    </div>
                    <Button type="submit">Buscar</Button>
                </form>
                <div className="flex flex-wrap gap-1.5">
                    {[
                        [false, 'Todos'],
                        [true, 'Solo pendientes'],
                    ].map(([value, label]) => (
                        <button
                            key={label}
                            type="button"
                            onClick={() => visit({ pendientes: value ? 1 : undefined, page: undefined })}
                            className={cx(
                                'rounded-full px-3 py-1.5 text-sm font-semibold',
                                Boolean(filters.pendientes) === value ? 'bg-primary text-on-primary' : 'bg-slate-100 text-slate-600 hover:bg-slate-200',
                            )}
                            aria-pressed={Boolean(filters.pendientes) === value}
                        >
                            {label}
                        </button>
                    ))}
                </div>
            </Card>

            {members.data.length === 0 ? (
                <EmptyState icon="Users" title="No hay socios que coincidan">
                    {filters.q || filters.pendientes ? 'Prueba con otra búsqueda o quita el filtro.' : 'Cuando tengas socios aparecerán aquí.'}
                </EmptyState>
            ) : (
                <Card className="p-0 sm:p-0">
                    <ul className="divide-y divide-slate-100">
                        {members.data.map((m) => (
                            <li key={m.id}>
                                <Link href={`/panel/valoraciones/${m.id}`} className="flex items-center gap-3 px-4 py-3 hover:bg-slate-50">
                                    <div className="grid size-10 shrink-0 place-items-center rounded-full bg-secondary font-bold text-on-secondary">
                                        {m.name.charAt(0)}
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate font-semibold">{m.name}</p>
                                        <p className="truncate text-sm text-slate-500">
                                            {m.lastMeasuredOn
                                                ? `Última: ${formatDate(m.lastMeasuredOn)} · hace ${m.daysSince} ${m.daysSince === 1 ? 'día' : 'días'}`
                                                : 'Sin valoraciones'}
                                        </p>
                                    </div>
                                    {m.pending ? <Badge tone="amber">Pendiente</Badge> : <Badge tone="green">Al día</Badge>}
                                    <ChevronRight className="size-4 shrink-0 text-slate-400" aria-hidden="true" />
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
