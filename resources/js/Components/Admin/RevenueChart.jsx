import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { formatMoney } from '@/lib/format';

const monthFmt = new Intl.DateTimeFormat('es-MX', { month: 'short' });
const monthYearFmt = new Intl.DateTimeFormat('es-MX', { month: 'long', year: 'numeric' });
const compact = new Intl.NumberFormat('es-MX', { notation: 'compact', maximumFractionDigits: 1 });

const toDate = (ym) => {
    const [y, m] = ym.split('-').map(Number);
    return new Date(y, m - 1, 1);
};

/** Ingresos cobrados por mes. Usa el color de la marca vía variable CSS. */
export default function RevenueChart({ data, height = 260 }) {
    const rows = data.map((d) => ({ ...d, label: monthFmt.format(toDate(d.month)).replace('.', '') }));

    return (
        <div style={{ height }} className="w-full min-w-0" role="img" aria-label="Gráfica de ingresos por mes">
            <ResponsiveContainer width="100%" height="100%">
                <BarChart data={rows} margin={{ top: 8, right: 4, left: -12, bottom: 0 }}>
                    <CartesianGrid vertical={false} stroke="#e2e8f0" />
                    <XAxis dataKey="label" tickLine={false} axisLine={false} fontSize={12} interval="preserveStartEnd" />
                    <YAxis tickLine={false} axisLine={false} fontSize={12} width={52} tickFormatter={(v) => '$' + compact.format(v)} />
                    <Tooltip
                        cursor={{ fill: 'rgba(148,163,184,0.15)' }}
                        formatter={(value, _name, item) => [formatMoney(value) + ' · ' + item.payload.count + ' pago(s)', 'Cobrado']}
                        labelFormatter={(_l, payload) => (payload?.[0] ? monthYearFmt.format(toDate(payload[0].payload.month)) : '')}
                    />
                    <Bar dataKey="total" fill="var(--brand-primary)" radius={[6, 6, 0, 0]} maxBarSize={40} />
                </BarChart>
            </ResponsiveContainer>
        </div>
    );
}
