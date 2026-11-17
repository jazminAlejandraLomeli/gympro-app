import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, ExternalLink, Pause, Play } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, Button, Card, EmptyState, Field, Input, PageHeader, Select, Table, Td, Textarea, Th } from '@/Components/ui';
import { BillingBadge, formatBytes, GymStatusBadge, Modal, relativeDays } from '@/Components/Admin';
import { formatDate, formatDateTime, formatMoney, formatNumber } from '@/lib/format';

/** Detalle de un gimnasio: datos SaaS, suspensión, pagos y referidos. */
export default function GymShow({ gym, admins, payments, referrals, referrerOptions, methods }) {
    const [confirmSuspend, setConfirmSuspend] = useState(false);
    const [paying, setPaying] = useState(false);
    const active = gym.status === 'active';
    const protocol = typeof window !== 'undefined' ? window.location.protocol : 'https:';
    const port = typeof window !== 'undefined' && window.location.port ? ':' + window.location.port : '';

    const setStatus = (action) =>
        router.post('/admin/gimnasios/' + gym.id + '/' + action, {}, { preserveScroll: true, onFinish: () => setConfirmSuspend(false) });

    return (
        <AppLayout title={gym.name}>
            <Link href="/admin/gimnasios" className="mb-3 inline-flex items-center gap-1 text-sm text-slate-600 hover:underline">
                <ArrowLeft className="size-4" aria-hidden="true" /> Gimnasios
            </Link>

            <PageHeader
                title={gym.name}
                description={
                    <a href={protocol + '//' + gym.host + port} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1 break-all hover:underline">
                        {gym.host} <ExternalLink className="size-3.5 shrink-0" aria-hidden="true" />
                    </a>
                }
                actions={
                    <>
                        <Button onClick={() => setPaying(true)}>Registrar pago</Button>
                        {active ? (
                            <Button variant="danger" onClick={() => setConfirmSuspend(true)}>
                                <Pause className="size-4" aria-hidden="true" /> Suspender
                            </Button>
                        ) : (
                            <Button variant="secondary" onClick={() => setStatus('activar')}>
                                <Play className="size-4" aria-hidden="true" /> Activar
                            </Button>
                        )}
                    </>
                }
            />

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <p className="text-sm text-slate-500">Estado</p>
                    <div className="mt-1 flex flex-wrap items-center gap-2">
                        <GymStatusBadge status={gym.status} />
                        {!active && gym.suspended_at && <span className="text-xs text-slate-500">desde {formatDateTime(gym.suspended_at)}</span>}
                    </div>
                </Card>
                <Card>
                    <p className="text-sm text-slate-500">Licencia pagada hasta</p>
                    <p className="mt-1 text-lg font-bold">{formatDate(gym.saas_paid_until)}</p>
                    <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                        <BillingBadge state={gym.billing} /> {relativeDays(gym.saas_paid_until)}
                    </div>
                </Card>
                <Card>
                    <p className="text-sm text-slate-500">Cuota neta mensual</p>
                    <p className="mt-1 text-lg font-bold">{formatMoney(gym.net_fee)}</p>
                    {gym.saas_discount > 0 && (
                        <p className="text-xs text-slate-500">
                            {formatMoney(gym.saas_monthly_fee)} − {formatMoney(gym.saas_discount)} de descuento
                        </p>
                    )}
                </Card>
                <Card>
                    <p className="text-sm text-slate-500">Uso</p>
                    <p className="mt-1 text-lg font-bold">{formatNumber(gym.members_count)} socios</p>
                    <p className="text-xs text-slate-500">
                        {formatNumber(gym.trainers_count)} entrenadores · {formatBytes(gym.storage_bytes)} multimedia
                    </p>
                </Card>
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-3">
                <div className="min-w-0 space-y-6 lg:col-span-2">
                    <Card>
                        <h2 className="mb-3 font-semibold">Pagos de licencia</h2>
                        {payments.length === 0 ? (
                            <EmptyState icon="Wallet" title="Sin pagos registrados" action={<Button onClick={() => setPaying(true)}>Registrar el primero</Button>}>
                                La licencia se activa con el primer pago.
                            </EmptyState>
                        ) : (
                            <Table>
                                <thead>
                                    <tr>
                                        <Th>Fecha</Th>
                                        <Th className="text-right">Monto</Th>
                                        <Th>Meses</Th>
                                        <Th>Cubre hasta</Th>
                                        <Th>Método</Th>
                                        <Th>Registró</Th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {payments.map((p) => (
                                        <tr key={p.id}>
                                            <Td>
                                                {formatDate(p.paid_at)}
                                                {p.notes && <p className="max-w-48 truncate text-xs text-slate-500" title={p.notes}>{p.notes}</p>}
                                            </Td>
                                            <Td className="text-right font-semibold">{formatMoney(p.amount)}</Td>
                                            <Td>{p.months}</Td>
                                            <Td>{formatDate(p.covers_until)}</Td>
                                            <Td>{p.method}</Td>
                                            <Td className="text-slate-500">{p.recorded_by ?? '—'}</Td>
                                        </tr>
                                    ))}
                                </tbody>
                            </Table>
                        )}
                    </Card>

                    <SaasForm gym={gym} referrerOptions={referrerOptions} />
                </div>

                <div className="space-y-6">
                    <Card>
                        <h2 className="mb-3 font-semibold">Referidos</h2>
                        {gym.referrer && (
                            <p className="mb-3 text-sm text-slate-600">
                                Llegó referido por{' '}
                                <Link href={'/admin/gimnasios/' + gym.referrer.id} className="font-semibold hover:underline">
                                    {gym.referrer.name}
                                </Link>
                                .
                            </p>
                        )}
                        <p className="text-sm text-slate-600">
                            Ha referido a <span className="font-bold text-slate-900">{referrals.length}</span> gimnasio(s).
                        </p>
                        {referrals.length > 0 && (
                            <ul className="mt-2 divide-y divide-slate-100">
                                {referrals.map((r) => (
                                    <li key={r.id} className="flex items-center justify-between gap-2 py-2 text-sm">
                                        <Link href={'/admin/gimnasios/' + r.id} className="truncate hover:underline">
                                            {r.name}
                                        </Link>
                                        <GymStatusBadge status={r.status} />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>

                    <Card>
                        <h2 className="mb-3 font-semibold">Administradores</h2>
                        {admins.length === 0 ? (
                            <p className="text-sm text-slate-500">Sin administradores.</p>
                        ) : (
                            <ul className="space-y-2 text-sm">
                                {admins.map((a) => (
                                    <li key={a.id} className="min-w-0">
                                        <p className="truncate font-medium">{a.name}</p>
                                        <p className="truncate text-slate-500">{a.email}</p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>

                    <Card>
                        <h2 className="mb-3 font-semibold">Marca</h2>
                        <div className="flex items-center gap-3">
                            {gym.logo_url ? (
                                <img src={gym.logo_url} alt="" className="size-10 rounded-full bg-white object-cover ring-1 ring-slate-200" />
                            ) : null}
                            <div className="flex gap-2">
                                {/* Colores del gimnasio (dato, no la marca de esta interfaz) */}
                                <span className="size-8 rounded-lg ring-1 ring-slate-200" style={{ background: gym.primary_color }} title={gym.primary_color} />
                                <span className="size-8 rounded-lg ring-1 ring-slate-200" style={{ background: gym.secondary_color }} title={gym.secondary_color} />
                            </div>
                            <Badge>{gym.slug}</Badge>
                        </div>
                        <p className="mt-2 text-xs text-slate-500">Alta: {formatDate(gym.created_at)}</p>
                    </Card>
                </div>
            </div>

            <Modal open={confirmSuspend} onClose={() => setConfirmSuspend(false)} title="¿Suspender este gimnasio?">
                <p className="text-sm text-slate-600">
                    El subdominio <span className="font-semibold">{gym.host}</span> dejará de dar servicio a su personal y socios hasta que lo actives o se registre un pago.
                </p>
                <div className="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <Button variant="secondary" onClick={() => setConfirmSuspend(false)}>
                        Cancelar
                    </Button>
                    <Button variant="danger" onClick={() => setStatus('suspender')}>
                        Suspender
                    </Button>
                </div>
            </Modal>

            <PaymentModal open={paying} onClose={() => setPaying(false)} gym={gym} methods={methods} />
        </AppLayout>
    );
}

function SaasForm({ gym, referrerOptions }) {
    const form = useForm({
        name: gym.name,
        saas_monthly_fee: gym.saas_monthly_fee,
        saas_discount: gym.saas_discount,
        referred_by_gym_id: gym.referred_by_gym_id ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.transform((d) => ({ ...d, referred_by_gym_id: d.referred_by_gym_id || null }));
        form.put('/admin/gimnasios/' + gym.id, { preserveScroll: true });
    };

    return (
        <Card>
            <h2 className="mb-3 font-semibold">Datos de la licencia</h2>
            <form onSubmit={submit} className="space-y-4">
                <Field label="Nombre" htmlFor="name" error={form.errors.name}>
                    <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                </Field>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Cuota mensual (MXN)" htmlFor="saas_monthly_fee" error={form.errors.saas_monthly_fee}>
                        <Input
                            id="saas_monthly_fee"
                            type="number"
                            inputMode="decimal"
                            min="0"
                            step="0.01"
                            value={form.data.saas_monthly_fee}
                            onChange={(e) => form.setData('saas_monthly_fee', e.target.value)}
                        />
                    </Field>
                    <Field label="Descuento mensual (MXN)" htmlFor="saas_discount" error={form.errors.saas_discount}>
                        <Input
                            id="saas_discount"
                            type="number"
                            inputMode="decimal"
                            min="0"
                            step="0.01"
                            value={form.data.saas_discount}
                            onChange={(e) => form.setData('saas_discount', e.target.value)}
                        />
                    </Field>
                </div>
                <Field label="Referido por" htmlFor="referred_by_gym_id" error={form.errors.referred_by_gym_id}>
                    <Select id="referred_by_gym_id" value={form.data.referred_by_gym_id} onChange={(e) => form.setData('referred_by_gym_id', e.target.value)}>
                        <option value="">Nadie</option>
                        {referrerOptions.map((g) => (
                            <option key={g.id} value={g.id}>
                                {g.name}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Button type="submit" disabled={form.processing || !form.isDirty}>
                    {form.processing ? 'Guardando…' : 'Guardar cambios'}
                </Button>
            </form>
        </Card>
    );
}

function todayIso() {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

function PaymentModal({ open, onClose, gym, methods }) {
    const form = useForm({
        months: 1,
        amount: gym.net_fee,
        paid_at: todayIso(),
        method: methods[0]?.value ?? 'transferencia',
        notes: '',
    });

    const close = () => {
        form.reset();
        form.clearErrors();
        onClose();
    };

    const setMonths = (v) => {
        const months = Math.max(1, Math.min(24, parseInt(v, 10) || 1));
        form.setData((d) => ({ ...d, months: v === '' ? '' : months, amount: Math.round(gym.net_fee * months * 100) / 100 }));
    };

    const submit = (e) => {
        e.preventDefault();
        form.post('/admin/gimnasios/' + gym.id + '/pagos', { preserveScroll: true, onSuccess: close });
    };

    return (
        <Modal open={open} onClose={close} title={'Registrar pago · ' + gym.name}>
            <form onSubmit={submit} className="space-y-4">
                <div className="grid grid-cols-2 gap-3">
                    <Field label="Meses" htmlFor="months" error={form.errors.months}>
                        <Input id="months" type="number" min="1" max="24" inputMode="numeric" value={form.data.months} onChange={(e) => setMonths(e.target.value)} />
                    </Field>
                    <Field label="Monto (MXN)" htmlFor="amount" error={form.errors.amount}>
                        <Input
                            id="amount"
                            type="number"
                            min="0"
                            step="0.01"
                            inputMode="decimal"
                            value={form.data.amount}
                            onChange={(e) => form.setData('amount', e.target.value)}
                        />
                    </Field>
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <Field label="Fecha de pago" htmlFor="paid_at" error={form.errors.paid_at}>
                        <Input id="paid_at" type="date" max={todayIso()} value={form.data.paid_at} onChange={(e) => form.setData('paid_at', e.target.value)} />
                    </Field>
                    <Field label="Método" htmlFor="method" error={form.errors.method}>
                        <Select id="method" value={form.data.method} onChange={(e) => form.setData('method', e.target.value)}>
                            {methods.map((m) => (
                                <option key={m.value} value={m.value}>
                                    {m.label}
                                </option>
                            ))}
                        </Select>
                    </Field>
                </div>
                <Field label="Notas (opcional)" htmlFor="notes" error={form.errors.notes}>
                    <Textarea id="notes" maxLength={500} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} placeholder="Referencia de la transferencia, folio…" />
                </Field>
                <p className="rounded-xl bg-slate-50 p-3 text-sm text-slate-600">
                    La licencia se extiende {form.data.months || 0} mes(es) a partir de{' '}
                    {gym.billing === 'ok' || gym.billing === 'expiring' ? 'su vencimiento actual (' + formatDate(gym.saas_paid_until) + ')' : 'hoy'}.
                    {gym.status !== 'active' && ' El gimnasio se reactivará.'}
                </p>
                <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <Button variant="secondary" onClick={close}>
                        Cancelar
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Guardando…' : 'Registrar pago'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
