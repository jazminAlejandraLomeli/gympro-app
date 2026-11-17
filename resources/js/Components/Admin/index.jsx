/**
 * Piezas del panel del super_admin (Módulo 0).
 */
import { Link } from '@inertiajs/react';
import { useEffect } from 'react';
import { AlertTriangle, ChevronRight, X } from 'lucide-react';
import { Badge, cx } from '@/Components/ui';
import { formatDate, formatMoney } from '@/lib/format';

export function GymStatusBadge({ status }) {
    return status === 'active' ? <Badge tone="green">Activo</Badge> : <Badge tone="red">Suspendido</Badge>;
}

const BILLING = {
    ok: { tone: 'green', label: 'Al corriente' },
    expiring: { tone: 'amber', label: 'Por vencer' },
    overdue: { tone: 'red', label: 'Vencido' },
    none: { tone: 'slate', label: 'Sin pagos' },
};

export function BillingBadge({ state }) {
    const b = BILLING[state] ?? BILLING.none;
    return <Badge tone={b.tone}>{b.label}</Badge>;
}

export function formatBytes(bytes) {
    const n = Number(bytes ?? 0);
    if (n < 1024) return `${n} B`;
    const units = ['KB', 'MB', 'GB', 'TB'];
    let v = n / 1024;
    let i = 0;
    while (v >= 1024 && i < units.length - 1) {
        v /= 1024;
        i++;
    }
    return `${v.toLocaleString('es-MX', { maximumFractionDigits: 1 })} ${units[i]}`;
}

/** Días entre hoy y una fecha 'YYYY-MM-DD' (negativo = ya pasó). */
export function daysUntil(isoDate) {
    if (!isoDate) return null;
    const [y, m, d] = isoDate.split('-').map(Number);
    const target = new Date(y, m - 1, d);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return Math.round((target - today) / 86400000);
}

export function relativeDays(isoDate) {
    const days = daysUntil(isoDate);
    if (days === null) return 'Sin pagos registrados';
    if (days === 0) return 'Vence hoy';
    if (days > 0) return days === 1 ? 'Vence mañana' : `Vence en ${days} días`;
    return days === -1 ? 'Venció ayer' : `Venció hace ${-days} días`;
}

/** Lista compacta de gimnasios (vencidos / por vencer). */
export function GymDueList({ gyms, empty }) {
    if (!gyms.length) return <p className="text-sm text-slate-500">{empty}</p>;
    return (
        <ul className="divide-y divide-slate-100">
            {gyms.map((g) => (
                <li key={g.id}>
                    <Link href={`/admin/gimnasios/${g.id}`} className="flex items-center justify-between gap-3 py-2.5 hover:bg-slate-50">
                        <div className="min-w-0">
                            <p className="truncate font-medium">{g.name}</p>
                            <p className="text-xs text-slate-500">
                                {relativeDays(g.saas_paid_until)}
                                {g.saas_paid_until && ` · ${formatDate(g.saas_paid_until)}`}
                            </p>
                        </div>
                        <div className="flex shrink-0 items-center gap-1 text-sm text-slate-600">
                            {formatMoney(g.net_fee)}
                            <ChevronRight className="size-4 text-slate-400" aria-hidden="true" />
                        </div>
                    </Link>
                </li>
            ))}
        </ul>
    );
}

const ALERT_TONES = {
    red: 'bg-red-50 text-red-800 ring-red-200',
    amber: 'bg-amber-50 text-amber-800 ring-amber-200',
};

export function AlertList({ alerts }) {
    if (!alerts.length) return null;
    return (
        <ul className="mb-5 space-y-2">
            {alerts.map((a) => (
                <li key={a.title}>
                    <Link href={a.href} className={cx('flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium ring-1', ALERT_TONES[a.tone] ?? ALERT_TONES.amber)}>
                        <AlertTriangle className="size-5 shrink-0" aria-hidden="true" />
                        <span className="min-w-0 flex-1">{a.title}</span>
                        <ChevronRight className="size-4 shrink-0" aria-hidden="true" />
                    </Link>
                </li>
            ))}
        </ul>
    );
}

/** Panel modal: hoja inferior en móvil, ventana centrada en escritorio. */
export function Modal({ open, onClose, title, children }) {
    useEffect(() => {
        if (!open) return;
        const onKey = (e) => e.key === 'Escape' && onClose();
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [open, onClose]);

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-40 flex items-end justify-center sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-label={title}>
            <div className="absolute inset-0 bg-black/40" onClick={onClose} />
            <div className="relative max-h-[92dvh] w-full overflow-y-auto rounded-t-2xl bg-white p-5 shadow-xl sm:max-w-lg sm:rounded-2xl">
                <div className="mb-4 flex items-center justify-between gap-3">
                    <h2 className="text-lg font-bold">{title}</h2>
                    <button type="button" onClick={onClose} className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100" aria-label="Cerrar">
                        <X className="size-5" aria-hidden="true" />
                    </button>
                </div>
                {children}
            </div>
        </div>
    );
}

/** Tarjeta de servicio en "Salud del sistema". */
export function HealthCheck({ check }) {
    return (
        <div className="flex items-start gap-3 rounded-xl p-3 ring-1 ring-slate-200">
            <span className={cx('mt-1.5 size-2.5 shrink-0 rounded-full', check.ok ? 'bg-green-500' : 'bg-red-500')} aria-hidden="true" />
            <div className="min-w-0 flex-1">
                <div className="flex items-center justify-between gap-2">
                    <p className="font-semibold">{check.label}</p>
                    <span className="shrink-0 text-xs text-slate-500">{check.ms} ms</span>
                </div>
                <p className={cx('text-sm break-words', check.ok ? 'text-slate-600' : 'text-red-700')}>
                    {check.ok ? check.detail : `Falla: ${check.detail}`}
                </p>
            </div>
        </div>
    );
}
