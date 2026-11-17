import { Badge, Card } from '@/Components/ui';
import { formatDate } from '@/lib/format';
import { formatMetric, metricByKey } from './utils';

const ORDER = ['weight_kg', 'body_fat_pct', 'waist_cm', 'chest_cm', 'arm_cm', 'leg_cm'];

/** Historial de valoraciones (más reciente primero), sin tablas anchas. */
export default function MeasurementHistory({ history, metrics, selfLabel = 'Tú', staffLabel = 'Entrenador' }) {
    const byKey = metricByKey(metrics);
    if (!history?.length) return null;
    const baselineId = history[history.length - 1].id;

    return (
        <section aria-labelledby="historial" className="space-y-3">
            <h2 id="historial" className="text-lg font-bold">
                Historial
            </h2>
            <ul className="space-y-3">
                {history.map((m) => (
                    <li key={m.id}>
                        <Card className="space-y-2">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <p className="font-semibold">{formatDate(m.measured_on)}</p>
                                <div className="flex gap-1.5">
                                    {m.id === baselineId && <Badge tone="primary">Línea base</Badge>}
                                    <Badge>{m.recorded_by_self ? selfLabel : staffLabel}</Badge>
                                </div>
                            </div>
                            <dl className="grid grid-cols-3 gap-x-3 gap-y-1.5 text-sm">
                                {ORDER.filter((k) => m[k] !== null).map((k) => (
                                    <div key={k} className="min-w-0">
                                        <dt className="truncate text-xs text-slate-500">{byKey[k].label}</dt>
                                        <dd className="font-semibold">{formatMetric(m[k], byKey[k].unit)}</dd>
                                    </div>
                                ))}
                            </dl>
                            {m.notes && <p className="text-sm break-words text-slate-600">{m.notes}</p>}
                        </Card>
                    </li>
                ))}
            </ul>
        </section>
    );
}
