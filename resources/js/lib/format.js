/** Formatos en español de México. */
const money = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });
const number = new Intl.NumberFormat('es-MX', { maximumFractionDigits: 1 });
const date = new Intl.DateTimeFormat('es-MX', { day: 'numeric', month: 'short', year: 'numeric' });
const dateTime = new Intl.DateTimeFormat('es-MX', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });

export const formatMoney = (v) => money.format(Number(v ?? 0));
export const formatNumber = (v) => number.format(Number(v ?? 0));

/** Acepta 'YYYY-MM-DD' (sin desfase de zona horaria) o ISO completo. */
function toDate(v) {
    if (!v) return null;
    if (/^\d{4}-\d{2}-\d{2}$/.test(v)) {
        const [y, m, d] = v.split('-').map(Number);
        return new Date(y, m - 1, d);
    }
    return new Date(v);
}

export const formatDate = (v) => (v ? date.format(toDate(v)) : '—');
export const formatDateTime = (v) => (v ? dateTime.format(toDate(v)) : '—');
