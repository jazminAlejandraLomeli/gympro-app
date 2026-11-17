import { useState } from 'react';
import { CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { Card, cx } from '@/Components/ui';
import { formatShortDate, formatMetric, metricByKey } from './utils';

const BODY_PARTS = ['waist_cm', 'chest_cm', 'arm_cm', 'leg_cm'];

function MetricChart({ data, metric }) {
    const points = data.filter((d) => d[metric.key] !== null && d[metric.key] !== undefined);

    if (points.length < 2) {
        return (
            <p className="grid h-48 place-items-center rounded-xl bg-slate-50 px-4 text-center text-sm text-slate-500">
                {points.length === 0 ? `Aún no hay registros de ${metric.label.toLowerCase()}.` : 'Con tu siguiente valoración verás aquí la tendencia.'}
            </p>
        );
    }

    return (
        <div className="h-52 w-full" role="img" aria-label={`Tendencia de ${metric.label.toLowerCase()}`}>
            <ResponsiveContainer width="100%" height="100%">
                <LineChart data={points} margin={{ top: 8, right: 8, bottom: 0, left: -12 }}>
                    <CartesianGrid stroke="#e2e8f0" strokeDasharray="3 3" vertical={false} />
                    <XAxis dataKey="date" tickFormatter={formatShortDate} tick={{ fontSize: 11, fill: '#64748b' }} tickLine={false} axisLine={false} minTickGap={16} />
                    <YAxis
                        domain={['auto', 'auto']}
                        tick={{ fontSize: 11, fill: '#64748b' }}
                        tickLine={false}
                        axisLine={false}
                        width={44}
                        allowDecimals={false}
                    />
                    <Tooltip
                        labelFormatter={formatShortDate}
                        formatter={(v) => [formatMetric(v, metric.unit), metric.label]}
                        contentStyle={{ borderRadius: 12, borderColor: '#e2e8f0', fontSize: 13 }}
                    />
                    <Line
                        type="monotone"
                        dataKey={metric.key}
                        stroke="var(--brand-primary)"
                        strokeWidth={3}
                        dot={{ r: 3, fill: 'var(--brand-primary)', strokeWidth: 0 }}
                        activeDot={{ r: 5 }}
                        isAnimationActive={false}
                    />
                </LineChart>
            </ResponsiveContainer>
        </div>
    );
}

/** Tendencias: peso, % de grasa y una medida corporal a elegir. */
export default function TrendCharts({ data, metrics }) {
    const byKey = metricByKey(metrics);
    const [part, setPart] = useState(BODY_PARTS[0]);

    if (!data?.length) return null;

    return (
        <section aria-labelledby="tendencias" className="space-y-3">
            <h2 id="tendencias" className="text-lg font-bold">
                Tendencias
            </h2>
            <div className="grid gap-4 lg:grid-cols-2">
                <Card>
                    <h3 className="mb-2 font-semibold">Peso (kg)</h3>
                    <MetricChart data={data} metric={byKey.weight_kg} />
                </Card>
                <Card>
                    <h3 className="mb-2 font-semibold">Grasa corporal (%)</h3>
                    <MetricChart data={data} metric={byKey.body_fat_pct} />
                </Card>
                <Card className="lg:col-span-2">
                    <div className="mb-3 flex flex-wrap gap-1.5" role="tablist" aria-label="Medida a graficar">
                        {BODY_PARTS.map((key) => (
                            <button
                                key={key}
                                type="button"
                                role="tab"
                                aria-selected={part === key}
                                onClick={() => setPart(key)}
                                className={cx(
                                    'rounded-full px-3 py-1.5 text-sm font-semibold transition',
                                    part === key ? 'bg-primary text-on-primary' : 'bg-slate-100 text-slate-600 hover:bg-slate-200',
                                )}
                            >
                                {byKey[key].label}
                            </button>
                        ))}
                    </div>
                    <MetricChart data={data} metric={byKey[part]} />
                </Card>
            </div>
        </section>
    );
}
