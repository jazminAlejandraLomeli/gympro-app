import { Link, router } from '@inertiajs/react';
import { CalendarClock, Pencil, Pin, Plus, Trash2 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, Button, Card, cx, EmptyState, PageHeader, Pagination } from '@/Components/ui';
import { formatDateTime } from '@/lib/format';

const STATUS = {
    programado: { label: 'Programado', tone: 'slate' },
    vigente: { label: 'Vigente', tone: 'green' },
    expirado: { label: 'Expirado', tone: 'red' },
};

const FILTERS = [
    { value: null, label: 'Todos' },
    { value: 'vigente', label: 'Vigentes' },
    { value: 'programado', label: 'Programados' },
    { value: 'expirado', label: 'Expirados' },
];

/** Tablón de avisos y promociones del gimnasio. */
export default function Index({ announcements, filters }) {
    const destroy = (a) => {
        if (!confirm(`¿Eliminar el aviso «${a.title}»? Esta acción no se puede deshacer.`)) return;
        router.delete(`/panel/avisos/${a.id}`, { preserveScroll: true });
    };

    return (
        <AppLayout title="Avisos">
            <PageHeader
                title="Avisos y promociones"
                description="Lo que publiques aquí aparece en el inicio de tus socios mientras esté vigente."
                actions={
                    <Button href="/panel/avisos/nuevo">
                        <Plus className="size-4" aria-hidden="true" /> Nuevo aviso
                    </Button>
                }
            />

            <div className="mb-4 flex flex-wrap gap-2" role="tablist" aria-label="Filtrar por estado">
                {FILTERS.map((f) => (
                    <Link
                        key={f.label}
                        href={f.value ? `/panel/avisos?status=${f.value}` : '/panel/avisos'}
                        preserveScroll
                        role="tab"
                        aria-selected={filters.status === f.value}
                        className={cx(
                            'rounded-full px-3.5 py-1.5 text-sm font-semibold',
                            filters.status === f.value ? 'bg-primary text-on-primary' : 'bg-white text-slate-600 ring-1 ring-slate-200',
                        )}
                    >
                        {f.label}
                    </Link>
                ))}
            </div>

            {announcements.data.length === 0 ? (
                <EmptyState
                    icon="Megaphone"
                    title="Sin avisos"
                    action={<Button href="/panel/avisos/nuevo">Publicar el primero</Button>}
                >
                    Publica horarios especiales, cierres o promociones para tus socios.
                </EmptyState>
            ) : (
                <div className="space-y-3">
                    {announcements.data.map((a) => (
                        <Card key={a.id} className={cx(a.status === 'expirado' && 'opacity-70')}>
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Badge tone={STATUS[a.status].tone}>{STATUS[a.status].label}</Badge>
                                        <Badge tone={a.type === 'promocion' ? 'primary' : 'slate'}>{a.typeLabel}</Badge>
                                        {a.pinned && (
                                            <span className="inline-flex items-center gap-1 text-xs font-semibold text-slate-500">
                                                <Pin className="size-3.5" aria-hidden="true" /> Fijado
                                            </span>
                                        )}
                                    </div>
                                    <h2 className="mt-2 font-semibold break-words">{a.title}</h2>
                                    <p className="mt-1 line-clamp-2 text-sm whitespace-pre-line break-words text-slate-600">{a.body}</p>
                                    <p className="mt-2 flex flex-wrap items-center gap-1 text-xs text-slate-500">
                                        <CalendarClock className="size-3.5" aria-hidden="true" />
                                        {formatDateTime(a.starts_at)} → {a.expires_at ? formatDateTime(a.expires_at) : 'sin fecha de fin'}
                                    </p>
                                </div>
                                <div className="flex shrink-0 gap-2">
                                    <Button variant="secondary" size="sm" href={`/panel/avisos/${a.id}/editar`}>
                                        <Pencil className="size-4" aria-hidden="true" /> Editar
                                    </Button>
                                    <Button variant="ghost" size="sm" onClick={() => destroy(a)} aria-label={`Eliminar ${a.title}`}>
                                        <Trash2 className="size-4 text-red-600" aria-hidden="true" />
                                    </Button>
                                </div>
                            </div>
                        </Card>
                    ))}
                </div>
            )}

            <Pagination links={announcements.links} />
        </AppLayout>
    );
}
