import { Sparkles, TrendingDown, TrendingUp } from 'lucide-react';
import { Card, cx } from '@/Components/ui';
import { formatDate } from '@/lib/format';
import { formatMetric } from './utils';

/** Logros contra la línea base (primer registro). */
export default function AchievementCards({ achievements, baseline, title = 'Tus logros' }) {
    if (!achievements?.length) return null;

    return (
        <section aria-labelledby="logros" className="space-y-3">
            <div className="flex items-baseline justify-between gap-2">
                <h2 id="logros" className="text-lg font-bold">
                    {title}
                </h2>
                {baseline && <p className="text-xs text-slate-500">Desde el {formatDate(baseline.measured_on)}</p>}
            </div>
            <ul className="grid gap-3 sm:grid-cols-2">
                {achievements.map((a) => {
                    const Arrow = a.delta < 0 ? TrendingDown : TrendingUp;
                    return (
                        <li key={a.metric}>
                            <Card className={cx('flex h-full items-start gap-3', a.positive && 'ring-2 ring-primary')}>
                                <div
                                    className={cx(
                                        'grid size-10 shrink-0 place-items-center rounded-xl',
                                        a.positive ? 'bg-primary text-on-primary' : 'bg-slate-100 text-slate-500',
                                    )}
                                >
                                    {a.positive ? <Sparkles className="size-5" aria-hidden="true" /> : <Arrow className="size-5" aria-hidden="true" />}
                                </div>
                                <div className="min-w-0">
                                    <p className="text-xl font-bold">
                                        {a.deltaLabel} <span className="text-sm font-medium text-slate-500">{a.label.toLowerCase()}</span>
                                    </p>
                                    <p className="text-sm text-slate-700">{a.text}</p>
                                    <p className="mt-1 text-xs text-slate-500">
                                        {formatMetric(a.from, a.unit)} → {formatMetric(a.to, a.unit)}
                                    </p>
                                </div>
                            </Card>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}
