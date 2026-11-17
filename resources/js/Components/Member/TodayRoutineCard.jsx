import { BedDouble, ChevronRight, Dumbbell } from 'lucide-react';
import { Button, Card } from '@/Components/ui';
import { formatWeight } from '@/Components/Training/days';

/**
 * Tarjeta "Tu rutina de hoy" para el inicio del socio.
 * Props: App\Services\Training\TodayRoutine::forMemberHome($user)
 *
 *   <TodayRoutineCard {...todayRoutine} />
 */
export default function TodayRoutineCard({ hasRoutine, routineName, dayName, isRestDay, exercises = [], href = '/app/rutina', max = 4 }) {
    const shown = exercises.slice(0, max);
    const more = exercises.length - shown.length;

    return (
        <Card>
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase">Hoy · {dayName}</p>
                    <h2 className="truncate text-lg font-bold">{hasRoutine ? routineName : 'Tu rutina'}</h2>
                </div>
                <div className="grid size-10 shrink-0 place-items-center rounded-xl bg-primary text-on-primary">
                    {isRestDay ? <BedDouble className="size-5" aria-hidden="true" /> : <Dumbbell className="size-5" aria-hidden="true" />}
                </div>
            </div>

            {!hasRoutine && <p className="mt-3 text-sm text-slate-600">Aún no tienes rutina asignada. Pídesela a tu entrenador.</p>}

            {isRestDay && (
                <p className="mt-3 text-sm text-slate-600">
                    <span className="font-semibold text-slate-900">Hoy es día de descanso.</span> Recupérate para la próxima sesión.
                </p>
            )}

            {shown.length > 0 && (
                <ul className="mt-3 divide-y divide-slate-100">
                    {shown.map((e) => (
                        <li key={e.id} className="flex items-center justify-between gap-3 py-2">
                            <div className="min-w-0">
                                <p className="truncate text-sm font-semibold">{e.name}</p>
                                <p className="text-xs text-slate-500">{e.muscle_group_label}</p>
                            </div>
                            <p className="shrink-0 text-right text-sm font-semibold">
                                {e.sets} × {e.reps}
                                {formatWeight(e.weight_kg) && <span className="block text-xs font-normal text-slate-500">{formatWeight(e.weight_kg)}</span>}
                            </p>
                        </li>
                    ))}
                </ul>
            )}
            {more > 0 && <p className="text-xs text-slate-500">y {more} más</p>}

            {hasRoutine && (
                <Button href={href} variant="secondary" className="mt-4 w-full">
                    Ver mi rutina <ChevronRight className="size-4" aria-hidden="true" />
                </Button>
            )}
        </Card>
    );
}
