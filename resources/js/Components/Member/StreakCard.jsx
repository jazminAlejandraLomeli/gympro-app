import { Link } from '@inertiajs/react';
import { ChevronRight, Flame } from 'lucide-react';
import { Card, cx } from '@/Components/ui';

/**
 * Racha de asistencia del socio (Módulo C).
 * Props: `streak` = StreakService::forMember(); `href` opcional para
 * enlazar a la pantalla de progreso (p. ej. desde el inicio).
 */
export default function StreakCard({ streak, href }) {
    if (!streak) return null;

    const { days, streakWeeks, attendedThisWeek, goal, goalMet, message, bestStreakWeeks } = streak;
    const percent = Math.min(100, Math.round((attendedThisWeek / goal) * 100));
    const weeksLabel = streakWeeks === 1 ? 'semana' : 'semanas';

    return (
        <Card className="space-y-4">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <h2 className="text-sm font-medium text-slate-500">Tu racha</h2>
                    <p className="mt-0.5 flex items-center gap-1.5 text-2xl font-bold" aria-label={`Racha de ${streakWeeks} ${weeksLabel}`}>
                        <span aria-hidden="true">🔥</span>
                        {streakWeeks} {weeksLabel}
                    </p>
                    {bestStreakWeeks > streakWeeks && (
                        <p className="text-xs text-slate-500">Tu mejor racha: {bestStreakWeeks} semanas</p>
                    )}
                </div>
                {href && (
                    <Link href={href} className="flex shrink-0 items-center gap-0.5 rounded-lg px-2 py-1 text-sm font-medium text-slate-600 hover:bg-slate-100">
                        Ver progreso <ChevronRight className="size-4" aria-hidden="true" />
                    </Link>
                )}
            </div>

            {/* Calendario de la semana: lunes a domingo */}
            <ol className="grid grid-cols-7 gap-1.5" aria-label="Asistencias de esta semana">
                {days.map((d) => (
                    <li key={d.date} className="flex flex-col items-center gap-1">
                        <span
                            className={cx(
                                'grid aspect-square w-full max-w-11 place-items-center rounded-xl text-sm font-semibold',
                                d.attended ? 'bg-primary text-on-primary' : d.isFuture ? 'bg-slate-50 text-slate-300' : 'bg-slate-100 text-slate-400',
                                d.isToday && 'ring-2 ring-primary ring-offset-2',
                            )}
                            title={d.attended ? 'Asististe' : d.isToday ? 'Hoy' : undefined}
                        >
                            {d.attended ? <Flame className="size-4" aria-hidden="true" /> : d.label}
                            <span className="sr-only">
                                {d.label} {d.attended ? 'asististe' : 'sin asistencia'}
                                {d.isToday ? ' (hoy)' : ''}
                            </span>
                        </span>
                        <span className={cx('text-[11px] font-medium', d.isToday ? 'text-slate-900' : 'text-slate-400')}>
                            {d.isToday ? 'Hoy' : d.label}
                        </span>
                    </li>
                ))}
            </ol>

            {/* Progreso de la meta semanal */}
            <div>
                <div className="mb-1.5 flex items-baseline justify-between text-sm">
                    <span className="font-semibold">
                        {Math.min(attendedThisWeek, goal)} de {goal} días
                    </span>
                    <span className={cx('text-xs font-medium', goalMet ? 'text-green-700' : 'text-slate-500')}>
                        {goalMet ? 'Meta cumplida' : 'Meta semanal'}
                    </span>
                </div>
                <div
                    className="h-2.5 overflow-hidden rounded-full bg-slate-100"
                    role="progressbar"
                    aria-valuemin={0}
                    aria-valuemax={goal}
                    aria-valuenow={Math.min(attendedThisWeek, goal)}
                    aria-label="Progreso de la meta semanal"
                >
                    <div className="h-full rounded-full bg-primary transition-all" style={{ width: `${percent}%` }} />
                </div>
            </div>

            <p className="text-sm text-slate-700">{message}</p>
        </Card>
    );
}
