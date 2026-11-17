import { Link } from '@inertiajs/react';
import { CalendarClock } from 'lucide-react';
import { Card } from '@/Components/ui';
import { formatDate } from '@/lib/format';

/**
 * Recordatorio amable de la valoración mensual (Módulo D).
 * Props: `reminder` = MeasurementService::reminder() (null → no se muestra).
 * `href` (o reminder.href) lleva al formulario; en la misma página usa '#registrar'.
 */
export default function MeasurementReminderCard({ reminder, href }) {
    if (!reminder) return null;

    const target = href ?? reminder.href ?? '/app/progreso#registrar';
    const isAnchor = target.startsWith('#');
    const buttonClass =
        'inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-on-primary transition hover:opacity-90 active:scale-[0.98] sm:w-auto';
    const label = reminder.reason === 'never' ? 'Registrar mis medidas' : 'Registrar valoración';

    return (
        <Card className="border-l-4 border-primary">
            <div className="flex gap-3">
                <div className="grid size-10 shrink-0 place-items-center rounded-xl bg-secondary text-on-secondary">
                    <CalendarClock className="size-5" aria-hidden="true" />
                </div>
                <div className="min-w-0 flex-1 space-y-3">
                    <div>
                        <h2 className="font-semibold">{reminder.title}</h2>
                        <p className="mt-1 text-sm text-slate-600">{reminder.message}</p>
                        {reminder.lastMeasuredOn && (
                            <p className="mt-1 text-xs text-slate-500">Última: {formatDate(reminder.lastMeasuredOn)}</p>
                        )}
                    </div>
                    {isAnchor ? (
                        <a href={target} className={buttonClass}>
                            {label}
                        </a>
                    ) : (
                        <Link href={target} className={buttonClass}>
                            {label}
                        </Link>
                    )}
                </div>
            </div>
        </Card>
    );
}
