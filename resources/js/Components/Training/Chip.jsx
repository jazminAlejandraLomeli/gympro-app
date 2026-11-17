import { cx } from '@/Components/ui';

/** Botón tipo píldora para filtros (grupo muscular, tipo, etc.). */
export default function Chip({ active, className, children, ...props }) {
    return (
        <button
            type="button"
            aria-pressed={active}
            className={cx(
                'min-h-9 rounded-full px-3 py-1.5 text-sm font-medium transition',
                active ? 'bg-primary text-on-primary' : 'bg-white text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50',
                className,
            )}
            {...props}
        >
            {children}
        </button>
    );
}
