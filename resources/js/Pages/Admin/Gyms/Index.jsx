import { Link, router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Plus, Search } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, Card, EmptyState, Field, Input, PageHeader, Pagination, Select, Table, Td, Th } from '@/Components/ui';
import { BillingBadge, GymStatusBadge, Modal, relativeDays } from '@/Components/Admin';
import { formatDate, formatMoney, formatNumber } from '@/lib/format';

const slugify = (v) =>
    v
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 63);

/** Gimnasios de la red: búsqueda, filtros y alta rápida. */
export default function GymsIndex({ gyms, filters, baseDomain, reserved, referrerOptions }) {
    const [creating, setCreating] = useState(false);

    return (
        <AppLayout title="Gimnasios">
            <PageHeader
                title="Gimnasios"
                description="Todos los gimnasios de la red, su licencia y su estado."
                actions={
                    <Button onClick={() => setCreating(true)}>
                        <Plus className="size-4" aria-hidden="true" /> Nuevo gimnasio
                    </Button>
                }
            />

            <Filters filters={filters} />

            {gyms.data.length === 0 ? (
                <EmptyState icon="Building2" title="No hay gimnasios con esos filtros">
                    Cambia la búsqueda o da de alta un gimnasio nuevo.
                </EmptyState>
            ) : (
                <>
                    {/* Móvil: tarjetas */}
                    <ul className="space-y-3 md:hidden">
                        {gyms.data.map((g) => (
                            <li key={g.id}>
                                <Link href={'/admin/gimnasios/' + g.id} className="block">
                                    <Card className="space-y-2">
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <p className="truncate font-semibold">{g.name}</p>
                                                <p className="truncate text-xs text-slate-500">{g.host}</p>
                                            </div>
                                            <GymStatusBadge status={g.status} />
                                        </div>
                                        <div className="flex flex-wrap items-center gap-2 text-sm text-slate-600">
                                            <BillingBadge state={g.billing} />
                                            <span>{relativeDays(g.saas_paid_until)}</span>
                                        </div>
                                        <p className="text-sm text-slate-600">
                                            {formatMoney(g.net_fee)}/mes · {formatNumber(g.members_count)} socios
                                        </p>
                                    </Card>
                                </Link>
                            </li>
                        ))}
                    </ul>

                    {/* Escritorio: tabla */}
                    <div className="hidden overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/60 md:block">
                        <Table>
                            <thead>
                                <tr>
                                    <Th>Gimnasio</Th>
                                    <Th>Estado</Th>
                                    <Th>Licencia</Th>
                                    <Th>Pagado hasta</Th>
                                    <Th className="text-right">Cuota neta</Th>
                                    <Th className="text-right">Socios</Th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {gyms.data.map((g) => (
                                    <tr key={g.id} className="hover:bg-slate-50">
                                        <Td>
                                            <Link href={'/admin/gimnasios/' + g.id} className="font-semibold hover:underline">
                                                {g.name}
                                            </Link>
                                            <p className="text-xs text-slate-500">{g.host}</p>
                                        </Td>
                                        <Td>
                                            <GymStatusBadge status={g.status} />
                                        </Td>
                                        <Td>
                                            <BillingBadge state={g.billing} />
                                        </Td>
                                        <Td>
                                            {formatDate(g.saas_paid_until)}
                                            <p className="text-xs text-slate-500">{relativeDays(g.saas_paid_until)}</p>
                                        </Td>
                                        <Td className="text-right">{formatMoney(g.net_fee)}</Td>
                                        <Td className="text-right">{formatNumber(g.members_count)}</Td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    </div>

                    <Pagination links={gyms.links} />
                </>
            )}

            <CreateGymModal
                open={creating}
                onClose={() => setCreating(false)}
                baseDomain={baseDomain}
                reserved={reserved}
                referrerOptions={referrerOptions}
            />
        </AppLayout>
    );
}

function Filters({ filters }) {
    const [q, setQ] = useState(filters.q);
    const first = useRef(true);

    const apply = (next) =>
        router.get(
            '/admin/gimnasios',
            Object.fromEntries(Object.entries({ ...filters, q, ...next }).filter(([, v]) => v !== '' && v != null)),
            { preserveState: true, preserveScroll: true, replace: true },
        );

    // Búsqueda al escribir (con pausa)
    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const t = setTimeout(() => apply({ q }), 350);
        return () => clearTimeout(t);
    }, [q]);

    return (
        <Card className="mb-4 grid gap-3 sm:grid-cols-[1fr_auto_auto]">
            <label className="relative block">
                <span className="sr-only">Buscar</span>
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                <Input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar por nombre o subdominio" className="pl-9" type="search" />
            </label>
            <Select aria-label="Estado" value={filters.status} onChange={(e) => apply({ status: e.target.value })}>
                <option value="">Todos los estados</option>
                <option value="active">Activos</option>
                <option value="suspended">Suspendidos</option>
            </Select>
            <Select aria-label="Vencimiento" value={filters.billing} onChange={(e) => apply({ billing: e.target.value })}>
                <option value="">Cualquier vencimiento</option>
                <option value="vencidos">Vencidos / sin pagos</option>
                <option value="por_vencer">Vencen en 7 días</option>
                <option value="al_dia">Al corriente</option>
            </Select>
        </Card>
    );
}

function CreateGymModal({ open, onClose, baseDomain, reserved, referrerOptions }) {
    const form = useForm({
        name: '',
        slug: '',
        primary_color: '#16a34a',
        secondary_color: '#0f172a',
        referred_by_gym_id: '',
        admin_name: '',
        admin_email: '',
        admin_password: '',
    });
    const [slugTouched, setSlugTouched] = useState(false);

    const close = () => {
        form.reset();
        form.clearErrors();
        setSlugTouched(false);
        onClose();
    };

    const setName = (name) => form.setData((d) => ({ ...d, name, slug: slugTouched ? d.slug : slugify(name) }));

    const submit = (e) => {
        e.preventDefault();
        form.transform((d) => ({ ...d, referred_by_gym_id: d.referred_by_gym_id || null }));
        form.post('/admin/gimnasios', { onSuccess: close });
    };

    const slugReserved = reserved.includes(form.data.slug);

    return (
        <Modal open={open} onClose={close} title="Nuevo gimnasio">
            <form onSubmit={submit} className="space-y-4">
                <Field label="Nombre del gimnasio" htmlFor="name" error={form.errors.name}>
                    <Input id="name" value={form.data.name} onChange={(e) => setName(e.target.value)} autoFocus required />
                </Field>

                <Field
                    label="Subdominio"
                    htmlFor="slug"
                    error={form.errors.slug ?? (slugReserved ? 'Ese subdominio está reservado.' : null)}
                    hint="Minúsculas, números y guiones."
                >
                    <div className="flex min-w-0 items-center rounded-xl ring-1 ring-slate-300 focus-within:ring-2 focus-within:ring-primary">
                        <input
                            id="slug"
                            value={form.data.slug}
                            onChange={(e) => {
                                setSlugTouched(true);
                                form.setData('slug', e.target.value.toLowerCase());
                            }}
                            className="min-w-0 flex-1 rounded-l-xl border-0 bg-transparent px-3.5 py-2.5 text-base focus:ring-0 sm:text-sm"
                            maxLength={63}
                            required
                        />
                        <span className="max-w-[45%] shrink-0 truncate pr-3.5 text-sm text-slate-500">.{baseDomain}</span>
                    </div>
                </Field>

                <div className="grid grid-cols-2 gap-3">
                    <ColorInput form={form} name="primary_color" label="Color principal" />
                    <ColorInput form={form} name="secondary_color" label="Color secundario" />
                </div>

                <Field label="Referido por (opcional)" htmlFor="referred_by_gym_id" error={form.errors.referred_by_gym_id}>
                    <Select id="referred_by_gym_id" value={form.data.referred_by_gym_id} onChange={(e) => form.setData('referred_by_gym_id', e.target.value)}>
                        <option value="">Nadie</option>
                        {referrerOptions.map((g) => (
                            <option key={g.id} value={g.id}>
                                {g.name}
                            </option>
                        ))}
                    </Select>
                </Field>

                <fieldset className="space-y-4 rounded-xl bg-slate-50 p-3">
                    <legend className="px-1 text-sm font-semibold text-slate-700">Administrador del gimnasio</legend>
                    <Field label="Nombre" htmlFor="admin_name" error={form.errors.admin_name}>
                        <Input id="admin_name" value={form.data.admin_name} onChange={(e) => form.setData('admin_name', e.target.value)} required />
                    </Field>
                    <Field label="Correo" htmlFor="admin_email" error={form.errors.admin_email}>
                        <Input
                            id="admin_email"
                            type="email"
                            autoComplete="off"
                            value={form.data.admin_email}
                            onChange={(e) => form.setData('admin_email', e.target.value)}
                            required
                        />
                    </Field>
                    <Field label="Contraseña inicial" htmlFor="admin_password" error={form.errors.admin_password} hint="Mínimo 8 caracteres. Pídele que la cambie.">
                        <Input
                            id="admin_password"
                            type="password"
                            autoComplete="new-password"
                            value={form.data.admin_password}
                            onChange={(e) => form.setData('admin_password', e.target.value)}
                            required
                        />
                    </Field>
                </fieldset>

                <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <Button variant="secondary" onClick={close}>
                        Cancelar
                    </Button>
                    <Button type="submit" disabled={form.processing || slugReserved}>
                        {form.processing ? 'Creando…' : 'Crear gimnasio'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}

function ColorInput({ form, name, label }) {
    return (
        <Field label={label} htmlFor={name} error={form.errors[name]}>
            <div className="flex items-center gap-2">
                <input
                    type="color"
                    aria-label={label}
                    value={/^#[0-9a-fA-F]{6}$/.test(form.data[name]) ? form.data[name] : '#000000'}
                    onChange={(e) => form.setData(name, e.target.value)}
                    className="h-10 w-11 shrink-0 cursor-pointer rounded-lg border border-slate-300"
                />
                <Input id={name} value={form.data[name]} onChange={(e) => form.setData(name, e.target.value)} maxLength={7} className="min-w-0 font-mono" />
            </div>
        </Field>
    );
}
