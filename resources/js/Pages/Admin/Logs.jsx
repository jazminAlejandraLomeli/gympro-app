import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { ChevronDown, RotateCcw, Trash2 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, Button, Card, cx, EmptyState, PageHeader, Select } from '@/Components/ui';
import { formatBytes, Modal } from '@/Components/Admin';
import { formatDateTime } from '@/lib/format';

const LEVEL_TONES = {
    EMERGENCY: 'red',
    ALERT: 'red',
    CRITICAL: 'red',
    ERROR: 'red',
    WARNING: 'amber',
    NOTICE: 'slate',
    INFO: 'slate',
    DEBUG: 'slate',
};

/** Centro de errores: final de laravel.log y trabajos fallidos. */
export default function Logs({ entries, logSize, maxBytes, failedJobs }) {
    const [level, setLevel] = useState('');
    const [forgetting, setForgetting] = useState(null);
    const [busy, setBusy] = useState(null);

    const levels = useMemo(() => [...new Set(entries.map((e) => e.level))], [entries]);
    const visible = level ? entries.filter((e) => e.level === level) : entries;

    const retry = (uuid) => {
        setBusy(uuid);
        router.post('/admin/errores/trabajos/' + uuid + '/reintentar', {}, { preserveScroll: true, onFinish: () => setBusy(null) });
    };
    const forget = () => {
        const uuid = forgetting.uuid;
        setBusy(uuid);
        router.delete('/admin/errores/trabajos/' + uuid, {
            preserveScroll: true,
            onFinish: () => {
                setBusy(null);
                setForgetting(null);
            },
        });
    };

    return (
        <AppLayout title="Errores">
            <PageHeader title="Centro de errores" description="Trabajos fallidos de la cola y las últimas entradas del log de la aplicación." />

            <Card>
                <h2 className="mb-3 font-semibold">Trabajos fallidos ({failedJobs.length})</h2>
                {failedJobs.length === 0 ? (
                    <p className="text-sm text-slate-500">No hay trabajos fallidos.</p>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {failedJobs.map((j) => (
                            <li key={j.uuid} className="py-3">
                                <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                    <div className="min-w-0">
                                        <p className="font-semibold break-words" title={j.job_full ?? undefined}>
                                            {j.job}
                                        </p>
                                        <p className="text-xs text-slate-500">
                                            {formatDateTime(j.failed_at)} · {j.connection}/{j.queue}
                                        </p>
                                        <p className="mt-1 text-sm break-words text-red-700">{j.message}</p>
                                    </div>
                                    <div className="flex shrink-0 gap-2">
                                        <Button size="sm" variant="secondary" disabled={busy === j.uuid} onClick={() => retry(j.uuid)}>
                                            <RotateCcw className="size-4" aria-hidden="true" /> Reintentar
                                        </Button>
                                        <Button size="sm" variant="ghost" disabled={busy === j.uuid} onClick={() => setForgetting(j)}>
                                            <Trash2 className="size-4" aria-hidden="true" /> Descartar
                                        </Button>
                                    </div>
                                </div>
                                {j.trace && <Trace text={j.trace} />}
                            </li>
                        ))}
                    </ul>
                )}
            </Card>

            <Card className="mt-6">
                <div className="mb-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="font-semibold">Log de la aplicación</h2>
                        <p className="text-xs text-slate-500">
                            laravel.log · {formatBytes(logSize)}
                            {logSize > maxBytes && ' · se muestran solo los últimos ' + formatBytes(maxBytes)}
                        </p>
                    </div>
                    <Select aria-label="Nivel" value={level} onChange={(e) => setLevel(e.target.value)} className="sm:w-48">
                        <option value="">Todos los niveles ({entries.length})</option>
                        {levels.map((l) => (
                            <option key={l} value={l}>
                                {l}
                            </option>
                        ))}
                    </Select>
                </div>

                {visible.length === 0 ? (
                    <EmptyState icon="Bug" title="Sin entradas">
                        El log está vacío o no hay entradas de ese nivel.
                    </EmptyState>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {visible.map((e) => (
                            <LogEntry key={e.id} entry={e} />
                        ))}
                    </ul>
                )}
            </Card>

            <Modal open={!!forgetting} onClose={() => setForgetting(null)} title="¿Descartar este trabajo?">
                <p className="text-sm text-slate-600">
                    Se borrará <span className="font-semibold">{forgetting?.job}</span> de la lista de fallidos y ya no se podrá reintentar.
                </p>
                <div className="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <Button variant="secondary" onClick={() => setForgetting(null)}>
                        Cancelar
                    </Button>
                    <Button variant="danger" onClick={forget} disabled={busy !== null}>
                        Descartar
                    </Button>
                </div>
            </Modal>
        </AppLayout>
    );
}

function LogEntry({ entry }) {
    const [open, setOpen] = useState(false);
    const hasTrace = !!entry.trace;

    return (
        <li className="py-3">
            <button
                type="button"
                onClick={() => hasTrace && setOpen((o) => !o)}
                className={cx('flex w-full items-start gap-3 text-left', hasTrace ? 'cursor-pointer' : 'cursor-default')}
                aria-expanded={hasTrace ? open : undefined}
            >
                <div className="w-20 shrink-0 pt-0.5">
                    <Badge tone={LEVEL_TONES[entry.level] ?? 'slate'}>{entry.level}</Badge>
                </div>
                <div className="min-w-0 flex-1">
                    <p className="text-sm break-words">{entry.message || '(sin mensaje)'}</p>
                    <p className="text-xs text-slate-500">
                        {formatDateTime(entry.date)} · {entry.env}
                    </p>
                </div>
                {hasTrace && <ChevronDown className={cx('mt-0.5 size-4 shrink-0 text-slate-400 transition', open && 'rotate-180')} aria-hidden="true" />}
            </button>
            {open && <Trace text={entry.trace} truncated={entry.trace_truncated} />}
        </li>
    );
}

function Trace({ text, truncated }) {
    return (
        <pre className="mt-2 max-h-80 overflow-auto rounded-xl bg-slate-900 p-3 text-xs leading-relaxed whitespace-pre-wrap text-slate-100 [overflow-wrap:anywhere]">
            {text}
            {truncated && '\n… (traza recortada)'}
        </pre>
    );
}
