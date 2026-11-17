import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Copy, KeyRound, Pencil, RefreshCw } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, Button, Card, EmptyState, PageHeader } from '@/Components/ui';
import PaymentForm from '@/Components/Membership/PaymentForm';
import StatusBadge from '@/Components/Membership/StatusBadge';
import { creditsLabel, daysLabel } from '@/Components/Membership/validity';
import { formatDate, formatDateTime, formatMoney } from '@/lib/format';

function Credentials({ credentials }) {
    const [copied, setCopied] = useState(false);
    if (!credentials) return null;

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(`Usuario: ${credentials.email}\nContraseña: ${credentials.password}`);
            setCopied(true);
        } catch {
            // portapapeles no disponible
        }
    };

    return (
        <div className="mb-5 rounded-2xl bg-amber-50 p-4 ring-1 ring-amber-200">
            <div className="flex items-start gap-3">
                <KeyRound className="mt-0.5 size-5 shrink-0 text-amber-700" aria-hidden="true" />
                <div className="min-w-0 flex-1 space-y-1">
                    <p className="font-semibold text-amber-900">Contraseña inicial (solo se muestra esta vez)</p>
                    <p className="text-sm break-all text-amber-900">Usuario: {credentials.email}</p>
                    <p className="font-mono text-2xl font-bold tracking-wider text-amber-950 select-all">{credentials.password}</p>
                </div>
            </div>
            <Button variant="secondary" size="sm" onClick={copy} className="mt-3">
                <Copy className="size-4" aria-hidden="true" /> {copied ? 'Copiado' : 'Copiar'}
            </Button>
        </div>
    );
}

function Row({ label, children }) {
    return (
        <div className="flex items-center justify-between gap-3 py-2">
            <dt className="text-sm text-slate-500">{label}</dt>
            <dd className="min-w-0 truncate text-right font-medium">{children}</dd>
        </div>
    );
}

/** Ficha del socio: estado, pago/renovación, historial de pagos y asistencias. */
export default function Show({ member, credentials, payments, attendances, attendancesThisMonth, methods }) {
    const regenerate = () => {
        if (confirm('¿Generar un QR nuevo? El QR actual del socio dejará de funcionar.')) {
            router.post(`/panel/socios/${member.id}/qr`, {}, { preserveScroll: true });
        }
    };

    return (
        <AppLayout title={member.name}>
            <PageHeader
                title={member.name}
                description={member.email}
                actions={
                    <>
                        <Button variant="secondary" href={`/panel/socios/${member.id}/editar`}>
                            <Pencil className="size-4" aria-hidden="true" /> Editar
                        </Button>
                        <Button variant="secondary" onClick={regenerate}>
                            <RefreshCw className="size-4" aria-hidden="true" /> Nuevo QR
                        </Button>
                    </>
                }
            />

            <Credentials credentials={credentials} />

            <div className="grid gap-6 lg:grid-cols-2">
                <div className="space-y-6">
                    <Card>
                        <div className="mb-2 flex items-center justify-between gap-3">
                            <h2 className="font-semibold">Membresía</h2>
                            <StatusBadge status={member.status} label={member.status_label} />
                        </div>
                        <dl className="divide-y divide-slate-100">
                            <Row label="Plan">{member.plan_name || '—'}</Row>
                            <Row label="Vigente hasta">
                                {formatDate(member.expires_on)}
                                {member.days_remaining !== null && <span className="ml-2 text-sm text-slate-500">({daysLabel(member.days_remaining)})</span>}
                            </Row>
                            <Row label="Créditos">{creditsLabel(member.credits)}</Row>
                            <Row label="Teléfono">{member.phone || '—'}</Row>
                            <Row label="Socio desde">{formatDate(member.member_since)}</Row>
                            <Row label="Asistencias este mes">{attendancesThisMonth}</Row>
                        </dl>
                    </Card>

                    <Card>
                        <h2 className="mb-4 font-semibold">Registrar pago / renovación</h2>
                        <PaymentForm membershipId={member.id} methods={methods} />
                    </Card>
                </div>

                <div className="space-y-6">
                    <Card>
                        <h2 className="mb-3 font-semibold">Pagos</h2>
                        {payments.length === 0 ? (
                            <EmptyState icon="Wallet" title="Sin pagos registrados" />
                        ) : (
                            <ul className="divide-y divide-slate-100">
                                {payments.map((p) => (
                                    <li key={p.id} className="flex items-start justify-between gap-3 py-3">
                                        <div className="min-w-0">
                                            <p className="truncate font-medium">{p.concept}</p>
                                            <p className="text-xs text-slate-500">
                                                {formatDateTime(p.paid_at)} · {p.method}
                                                {p.recorded_by ? ` · ${p.recorded_by}` : ''}
                                            </p>
                                        </div>
                                        <p className="shrink-0 font-semibold">{formatMoney(p.amount)}</p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>

                    <Card>
                        <h2 className="mb-3 font-semibold">Asistencias recientes</h2>
                        {attendances.length === 0 ? (
                            <EmptyState icon="ScanLine" title="Sin asistencias" />
                        ) : (
                            <ul className="divide-y divide-slate-100">
                                {attendances.map((a) => (
                                    <li key={a.id} className="flex items-center justify-between gap-3 py-2.5">
                                        <span className="text-sm">{formatDateTime(a.scanned_at)}</span>
                                        <span className="flex gap-1.5">
                                            <Badge>{a.method}</Badge>
                                            {a.credits_used > 0 && <Badge tone="amber">−{a.credits_used} crédito</Badge>}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
