import { Badge, cx } from '@/Components/ui';
import { formatDateTime, formatMoney } from '@/lib/format';

const TONES = { entrada: 'green', salida: 'red', ajuste: 'amber', venta: 'primary' };

/** Lista de movimientos de existencias (sin tabla: se lee bien a 375px). */
export default function MovementList({ movements, showProduct = true }) {
    if (!movements.length) return <p className="text-sm text-slate-500">Sin movimientos.</p>;

    return (
        <ul className="divide-y divide-slate-100">
            {movements.map((m) => (
                <li key={m.id} className="flex items-start gap-3 py-2.5">
                    <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <Badge tone={TONES[m.type]}>{m.typeLabel}</Badge>
                            {showProduct && <span className="text-sm font-medium break-words">{m.product}</span>}
                        </div>
                        <p className="mt-1 text-xs text-slate-500">
                            {formatDateTime(m.created_at)}
                            {m.user && ` · ${m.user}`}
                            {m.member && ` · Socio: ${m.member}`}
                        </p>
                        {m.note && <p className="mt-0.5 text-xs break-words text-slate-600">{m.note}</p>}
                    </div>
                    <div className="shrink-0 text-right">
                        <p className={cx('font-bold tabular-nums', m.quantity > 0 ? 'text-green-700' : 'text-slate-900')}>
                            {m.quantity > 0 ? `+${m.quantity}` : m.quantity}
                        </p>
                        {m.total !== null && m.total !== undefined && <p className="text-xs text-slate-500">{formatMoney(m.total)}</p>}
                    </div>
                </li>
            ))}
        </ul>
    );
}
