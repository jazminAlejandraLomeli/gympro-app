import { cx } from '@/Components/ui';
import { DAYS } from './days';

/**
 * Pestañas L M X J V S D. `counts` (opcional) = { 1: 3, 3: 4 } muestra
 * cuántos ejercicios hay por día; `today` marca el día de hoy con un punto.
 */
export default function DayTabs({ value, onChange, counts = {}, today, label = 'Día de la semana' }) {
    return (
        <div role="tablist" aria-label={label} className="grid grid-cols-7 gap-1.5">
            {DAYS.map(({ day, short, name }) => {
                const selected = value === day;
                const count = counts[day] ?? 0;
                return (
                    <button
                        key={day}
                        type="button"
                        role="tab"
                        aria-selected={selected}
                        aria-label={`${name}${count ? `, ${count} ejercicios` : ''}${today === day ? ', hoy' : ''}`}
                        onClick={() => onChange(day)}
                        className={cx(
                            'relative flex aspect-square min-h-11 flex-col items-center justify-center rounded-xl text-sm font-bold transition',
                            selected ? 'bg-primary text-on-primary shadow-sm' : count ? 'bg-slate-200 text-slate-800' : 'bg-slate-100 text-slate-400',
                        )}
                    >
                        {short}
                        {count > 0 && <span className="text-[10px] leading-none font-medium opacity-80">{count}</span>}
                        {today === day && (
                            <span className={cx('absolute top-1 right-1 size-1.5 rounded-full', selected ? 'bg-on-primary' : 'bg-primary')} />
                        )}
                    </button>
                );
            })}
        </div>
    );
}
