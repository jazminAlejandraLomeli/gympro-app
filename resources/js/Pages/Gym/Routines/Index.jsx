import { Link, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { CalendarDays, Copy, Dumbbell, FilePlus2, Inbox, LayoutTemplate, Pencil, Search, Send, Trash2, UserRound, X } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, Button, Card, cx, EmptyState, Field, Input, PageHeader, Pagination, Select } from '@/Components/ui';
import { formatDate } from '@/lib/format';
import { MemberMultiSelect } from '@/Components/Training/MemberPicker';
import Sheet from '@/Components/Training/Sheet';
import { timeAgo, TYPE_LABELS } from '@/Components/Training/days';

const TABS = [
    { value: 'solicitudes', label: 'Solicitudes', icon: Inbox },
    { value: 'plantillas', label: 'Plantillas', icon: LayoutTemplate },
    { value: 'historial', label: 'Historial', icon: CalendarDays },
];

/** Rutinas del gimnasio: solicitudes de socios, plantillas e historial. */
export default function RoutinesIndex({ tab, pendingCount, today, filters, templates, requests, members, history }) {
    return (
        <AppLayout title="Rutinas">
            <PageHeader
                title="Rutinas"
                description="Atiende lo que piden tus socios, guarda plantillas y consulta todo lo que se ha asignado."
                actions={
                    <Button href="/panel/rutinas/nueva">
                        <FilePlus2 className="size-4" aria-hidden="true" /> Nueva rutina
                    </Button>
                }
            />

            <nav className="mb-5 grid grid-cols-3 gap-1 rounded-2xl bg-slate-100 p-1" aria-label="Secciones de rutinas">
                {TABS.map(({ value, label, icon: IconCmp }) => {
                    const active = tab === value;
                    return (
                        <Link
                            key={value}
                            href={`/panel/rutinas?tab=${value}`}
                            preserveScroll
                            aria-current={active ? 'page' : undefined}
                            className={cx(
                                'flex min-h-11 items-center justify-center gap-1.5 rounded-xl px-2 py-2 text-sm font-semibold transition',
                                active ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800',
                            )}
                        >
                            <IconCmp className="hidden size-4 sm:block" aria-hidden="true" />
                            {label}
                            {value === 'solicitudes' && pendingCount > 0 && (
                                <span className="grid min-w-5 place-items-center rounded-full bg-primary px-1.5 text-xs leading-5 text-on-primary">{pendingCount}</span>
                            )}
                        </Link>
                    );
                })}
            </nav>

            {tab === 'solicitudes' && <RequestsTab requests={requests} templates={templates} today={today} />}
            {tab === 'plantillas' && <TemplatesTab templates={templates} members={members} today={today} />}
            {tab === 'historial' && <HistoryTab history={history} filters={filters} />}
        </AppLayout>
    );
}

/* ── Solicitudes ─────────────────────────────────────────────────── */

function RequestsTab({ requests, templates, today }) {
    const [assigning, setAssigning] = useState(null);

    if (requests.length === 0) {
        return (
            <EmptyState icon="Inbox" title="Sin solicitudes pendientes">
                Cuando un socio pida una rutina desde su app, aparecerá aquí.
            </EmptyState>
        );
    }

    const dismiss = (r) => {
        if (confirm(`¿Descartar la solicitud de ${r.user.name}?`)) {
            router.post(`/panel/rutinas/solicitudes/${r.id}/descartar`, {}, { preserveScroll: true });
        }
    };

    return (
        <>
            <ul className="grid gap-4 lg:grid-cols-2">
                {requests.map((r) => (
                    <li key={r.id}>
                        <Card className="flex h-full flex-col gap-3">
                            <div className="flex items-start gap-3">
                                <span className="grid size-10 shrink-0 place-items-center rounded-full bg-slate-100 text-slate-600">
                                    <UserRound className="size-5" aria-hidden="true" />
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-semibold">{r.user.name}</p>
                                    <p className="text-xs text-slate-500">Pidió {timeAgo(r.created_at)}</p>
                                </div>
                                <Badge tone={r.kind === 'single' ? 'amber' : 'primary'}>{r.kind === 'single' ? 'Un día' : 'Semana'}</Badge>
                            </div>
                            <p className="rounded-xl bg-slate-50 px-3 py-2 text-sm whitespace-pre-line text-slate-700">“{r.goal}”</p>
                            {r.days_per_week && <p className="text-sm text-slate-600">Puede venir {r.days_per_week} días por semana.</p>}
                            <div className="mt-auto grid gap-2 sm:grid-cols-3">
                                <Button href={`/panel/rutinas/nueva?tipo=${r.kind === 'single' ? 'dia' : 'semana'}&socio=${r.user.id}&solicitud=${r.id}`}>
                                    <FilePlus2 className="size-4" aria-hidden="true" /> Crear rutina
                                </Button>
                                <Button variant="secondary" onClick={() => setAssigning(r)} disabled={templates.length === 0}>
                                    <LayoutTemplate className="size-4" aria-hidden="true" /> Asignar existente
                                </Button>
                                <Button variant="ghost" onClick={() => dismiss(r)} className="text-red-600">
                                    <X className="size-4" aria-hidden="true" /> Descartar
                                </Button>
                            </div>
                        </Card>
                    </li>
                ))}
            </ul>
            {assigning && <AssignExistingSheet request={assigning} templates={templates} today={today} onClose={() => setAssigning(null)} />}
        </>
    );
}

function AssignExistingSheet({ request, templates, today, onClose }) {
    // Primero las plantillas del tipo que pidió
    const sorted = useMemo(() => [...templates].sort((a, b) => (a.type === request.kind ? -1 : 0) - (b.type === request.kind ? -1 : 0)), [templates, request]);
    const form = useForm({ template_id: sorted[0]?.id ?? '', date: today });
    const chosen = templates.find((t) => t.id === Number(form.data.template_id));

    const submit = () => form.post(`/panel/rutinas/solicitudes/${request.id}/asignar`, { preserveScroll: true, onSuccess: onClose });

    return (
        <Sheet
            open
            onClose={onClose}
            title="Asignar una plantilla"
            description={`Se crea una copia personal para ${request.user.name}.`}
            footer={
                <Button size="lg" onClick={submit} disabled={form.processing || !form.data.template_id}>
                    <Send className="size-5" aria-hidden="true" /> {form.processing ? 'Asignando…' : 'Asignar y marcar atendida'}
                </Button>
            }
        >
            <fieldset className="space-y-2">
                <legend className="mb-2 text-sm font-medium text-slate-700">Plantilla</legend>
                {sorted.map((t) => (
                    <label
                        key={t.id}
                        className={cx(
                            'flex min-h-14 cursor-pointer items-center gap-3 rounded-xl bg-white px-3 py-2 ring-1',
                            Number(form.data.template_id) === t.id ? 'ring-2 ring-primary' : 'ring-slate-200',
                        )}
                    >
                        <input type="radio" name="template" className="size-5 shrink-0" checked={Number(form.data.template_id) === t.id} onChange={() => form.setData('template_id', t.id)} />
                        <span className="min-w-0 flex-1">
                            <span className="block truncate font-medium">{t.name}</span>
                            <span className="block truncate text-xs text-slate-500">
                                {t.type_label} · {t.items_count} ejercicios{t.goal ? ` · ${t.goal}` : ''}
                            </span>
                        </span>
                    </label>
                ))}
            </fieldset>
            {form.errors.template_id && <p className="mt-2 text-sm text-red-600">{form.errors.template_id}</p>}
            {chosen && (
                <Field className="mt-4" label={chosen.type === 'single' ? 'Fecha de la sesión' : 'Empieza el'} htmlFor="assign-date" error={form.errors.date}>
                    <Input id="assign-date" type="date" min={today} value={form.data.date} onChange={(e) => form.setData('date', e.target.value)} />
                </Field>
            )}
        </Sheet>
    );
}

/* ── Plantillas ──────────────────────────────────────────────────── */

function TemplatesTab({ templates, members, today }) {
    const [assigning, setAssigning] = useState(null);

    if (templates.length === 0) {
        return (
            <EmptyState
                icon="ClipboardList"
                title="Aún no hay plantillas"
                action={<Button href="/panel/rutinas/nueva?tipo=semana">Crear plantilla</Button>}
            >
                Una plantilla es una rutina reutilizable: la armas una vez y se la asignas a quien la necesite.
            </EmptyState>
        );
    }

    const duplicate = (t) => router.post(`/panel/rutinas/${t.id}/duplicar`);
    const destroy = (t) => {
        if (confirm(`¿Borrar la plantilla «${t.name}»? Los socios que ya la tienen conservan su copia.`)) {
            router.delete(`/panel/rutinas/${t.id}`, { preserveScroll: true });
        }
    };

    return (
        <>
            <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {templates.map((t) => (
                    <li key={t.id}>
                        <Card className="flex h-full flex-col gap-3">
                            <div className="flex items-start justify-between gap-2">
                                <div className="min-w-0">
                                    <h3 className="leading-tight font-semibold">{t.name}</h3>
                                    {t.goal && <p className="text-sm text-slate-500">{t.goal}</p>}
                                </div>
                                <Badge tone={t.type === 'single' ? 'amber' : 'slate'}>{t.type_label}</Badge>
                            </div>
                            <p className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                                <span className="inline-flex items-center gap-1">
                                    <Dumbbell className="size-3.5" aria-hidden="true" /> {t.items_count} ejercicios
                                </span>
                                {t.days_count !== null && <span>{t.days_count} días de entrenamiento</span>}
                                {t.creator && <span>Por {t.creator}</span>}
                            </p>
                            <div className="mt-auto grid grid-cols-2 gap-2 border-t border-slate-100 pt-3">
                                <Button onClick={() => setAssigning(t)} className="col-span-2">
                                    <Send className="size-4" aria-hidden="true" /> Asignar a socios
                                </Button>
                                <Button href={`/panel/rutinas/${t.id}/editar`} variant="secondary" size="sm" className="py-2.5">
                                    <Pencil className="size-4" aria-hidden="true" /> Editar
                                </Button>
                                <Button variant="secondary" size="sm" onClick={() => duplicate(t)} className="py-2.5">
                                    <Copy className="size-4" aria-hidden="true" /> Duplicar
                                </Button>
                                <Button variant="ghost" size="sm" onClick={() => destroy(t)} className="col-span-2 text-red-600">
                                    <Trash2 className="size-4" aria-hidden="true" /> Borrar
                                </Button>
                            </div>
                        </Card>
                    </li>
                ))}
            </ul>
            {assigning && <AssignTemplateSheet template={assigning} members={members} today={today} onClose={() => setAssigning(null)} />}
        </>
    );
}

function AssignTemplateSheet({ template, members, today, onClose }) {
    const form = useForm({ user_ids: [], date: today });
    const n = form.data.user_ids.length;
    const memberError = Object.entries(form.errors).find(([k]) => k.startsWith('user_ids'))?.[1];
    const isSingle = template.type === 'single';

    const submit = () => form.post(`/panel/rutinas/${template.id}/asignaciones`, { preserveScroll: true, onSuccess: onClose });

    return (
        <Sheet
            open
            onClose={onClose}
            title={`Asignar «${template.name}»`}
            description={
                isSingle
                    ? 'Cada socio recibe su copia para la fecha elegida. Si ya tenía otra sesión ese día, se reemplaza.'
                    : 'Cada socio recibe su copia. Reemplaza solo su rutina semanal actual.'
            }
            footer={
                <Button size="lg" onClick={submit} disabled={form.processing || n === 0}>
                    {n === 0 ? 'Elige socios' : n === 1 ? 'Asignar a 1 socio' : `Asignar a ${n} socios`}
                </Button>
            }
        >
            <div className="space-y-4">
                <Field label={isSingle ? 'Fecha de la sesión' : 'Empieza el'} htmlFor="tpl-date" error={form.errors.date}>
                    <Input id="tpl-date" type="date" min={today} value={form.data.date} onChange={(e) => form.setData('date', e.target.value)} />
                </Field>
                <Field label="Socios" error={memberError}>
                    <MemberMultiSelect members={members} value={form.data.user_ids} onChange={(ids) => form.setData('user_ids', ids)} />
                </Field>
            </div>
        </Sheet>
    );
}

/* ── Historial ───────────────────────────────────────────────────── */

function HistoryTab({ history, filters }) {
    const [q, setQ] = useState(filters.q);

    const apply = (next) =>
        router.get('/panel/rutinas', { tab: 'historial', ...filters, q, ...next }, { preserveState: true, preserveScroll: true, replace: true });

    return (
        <div className="space-y-4">
            <div className="grid gap-2 sm:grid-cols-[minmax(0,1fr)_12rem_12rem]">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        apply({});
                    }}
                    className="relative"
                >
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                    <Input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar por socio" aria-label="Buscar por socio" className="pl-9" />
                </form>
                <Select value={filters.creador} onChange={(e) => apply({ creador: e.target.value })} aria-label="Quién la creó">
                    <option value="">Creada por: todos</option>
                    <option value="coach">Por un coach</option>
                    <option value="socio">Por el socio</option>
                </Select>
                <Select value={filters.tipo} onChange={(e) => apply({ tipo: e.target.value })} aria-label="Tipo de rutina">
                    <option value="">Todos los tipos</option>
                    <option value="single">{TYPE_LABELS.single}</option>
                    <option value="weekly">{TYPE_LABELS.weekly}</option>
                </Select>
            </div>

            {history.data.length === 0 ? (
                <EmptyState icon="ClipboardList" title="Sin rutinas">
                    {filters.q || filters.creador || filters.tipo ? 'Prueba con otro filtro.' : 'Aquí aparecerán las rutinas personales de tus socios.'}
                </EmptyState>
            ) : (
                <ul className="divide-y divide-slate-100 rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/60">
                    {history.data.map((r) => (
                        <li key={r.id}>
                            <Link href={`/panel/rutinas/${r.id}/editar`} className="flex items-center gap-3 px-4 py-3 hover:bg-slate-50">
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-semibold">{r.member?.name ?? 'Socio eliminado'}</p>
                                    <p className="truncate text-sm text-slate-600">{r.name}</p>
                                    <p className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                                        <span>{r.by_member ? 'Creada por el socio' : `Por ${r.author}`}</span>
                                        <span>{formatDate(r.created_at)}</span>
                                        <span>{r.items_count} ejercicios</span>
                                    </p>
                                </div>
                                <div className="flex shrink-0 flex-col items-end gap-1">
                                    <Badge tone={r.type === 'single' ? 'amber' : 'slate'}>{r.type_label}</Badge>
                                    {r.is_current && <Badge tone="green">Vigente</Badge>}
                                </div>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}

            <Pagination links={history.links} />
        </div>
    );
}
