import { formatNumber } from '@/lib/format';

const shortDate = new Intl.DateTimeFormat('es-MX', { day: 'numeric', month: 'short' });

/** 'YYYY-MM-DD' → "16 nov" (sin desfase de zona horaria). */
export function formatShortDate(v) {
    if (!v) return '';
    const [y, m, d] = v.slice(0, 10).split('-').map(Number);
    return shortDate.format(new Date(y, m - 1, d));
}

/** 84.5 + 'kg' → "84.5 kg"; '%' sin espacio. */
export function formatMetric(value, unit) {
    if (value === null || value === undefined) return '—';
    return unit === '%' ? `${formatNumber(value)}%` : `${formatNumber(value)} ${unit}`;
}

export const metricByKey = (metrics) => Object.fromEntries(metrics.map((m) => [m.key, m]));
