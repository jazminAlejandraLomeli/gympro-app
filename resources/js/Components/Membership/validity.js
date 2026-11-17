import { formatDate } from '@/lib/format';

/** "Vence hoy", "Quedan 12 días", "Vence el 3 dic 2026"… */
export function daysLabel(days) {
    if (days === null || days === undefined) return null;
    if (days === 0) return 'Vence hoy';
    if (days === 1) return 'Queda 1 día';
    return `Quedan ${days} días`;
}

export function creditsLabel(credits) {
    return credits === 1 ? '1 crédito' : `${credits ?? 0} créditos`;
}

/** Resumen corto para listas: vigencia por fecha y/o créditos. */
export function validitySummary({ expires_on, days_remaining, credits }) {
    const parts = [];
    if (days_remaining !== null && days_remaining !== undefined) {
        parts.push(`Vence ${formatDate(expires_on)}`);
    } else if (expires_on) {
        parts.push(`Venció ${formatDate(expires_on)}`);
    }
    if (credits > 0 || parts.length === 0) parts.push(creditsLabel(credits));
    return parts.join(' · ');
}
