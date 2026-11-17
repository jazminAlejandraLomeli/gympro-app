/** Días ISO (1 = lunes) y formatos del módulo de rutinas. */
import { formatNumber } from '@/lib/format';

export const DAYS = [
    { day: 1, short: 'L', name: 'Lunes' },
    { day: 2, short: 'M', name: 'Martes' },
    { day: 3, short: 'X', name: 'Miércoles' },
    { day: 4, short: 'J', name: 'Jueves' },
    { day: 5, short: 'V', name: 'Viernes' },
    { day: 6, short: 'S', name: 'Sábado' },
    { day: 7, short: 'D', name: 'Domingo' },
];

/** 90 → "1:30 min", 45 → "45 s". */
export function formatRest(seconds) {
    if (seconds === null || seconds === undefined || seconds === '') return null;
    const s = Number(seconds);
    if (s <= 0) return 'Sin descanso';
    if (s < 60) return `${s} s`;
    const m = Math.floor(s / 60);
    const r = s % 60;
    return r ? `${m}:${String(r).padStart(2, '0')} min` : `${m} min`;
}

export const formatWeight = (kg) => (kg === null || kg === undefined || kg === '' ? null : `${formatNumber(kg)} kg`);

/** 1536000 → "1.5 MB" */
export function formatBytes(bytes) {
    if (!bytes) return null;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${formatNumber(bytes / (1024 * 1024))} MB`;
}
