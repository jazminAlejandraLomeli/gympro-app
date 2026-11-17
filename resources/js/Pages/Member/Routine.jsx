import { useState } from 'react';
import { BedDouble } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, EmptyState } from '@/Components/ui';
import { formatDate } from '@/lib/format';
import DayTabs from '@/Components/Training/DayTabs';
import RoutineExerciseCard from '@/Components/Training/RoutineExerciseCard';

/** Rutina activa del socio: hoy seleccionado y pestañas para el resto de la semana. */
export default function Routine({ routine, today, days }) {
    const [day, setDay] = useState(today);

    if (!routine) {
        return (
            <AppLayout title="Mi rutina">
                <h1 className="text-2xl font-bold">Mi rutina</h1>
                <EmptyState icon="Dumbbell" title="Aún no tienes rutina">
                    Pídele a tu entrenador que te arme una. En cuanto te la asigne, aparecerá aquí con videos de cada ejercicio.
                </EmptyState>
            </AppLayout>
        );
    }

    const current = days.find((d) => d.day === day) ?? days[0];
    const counts = Object.fromEntries(days.map((d) => [d.day, d.items.length]));
    const startsLater = routine.starts_on > new Date().toISOString().slice(0, 10);

    return (
        <AppLayout title="Mi rutina">
            <div>
                <h1 className="text-2xl font-bold">{routine.name}</h1>
                {routine.goal && <p className="text-sm text-slate-500">{routine.goal}</p>}
                {startsLater && <p className="mt-1 text-sm font-medium text-amber-700">Empieza el {formatDate(routine.starts_on)}</p>}
            </div>

            <DayTabs value={day} onChange={setDay} counts={counts} today={today} />

            <h2 className="text-lg font-semibold">
                {day === today ? `Hoy · ${current.name}` : current.name}
                {current.items.length > 0 && (
                    <span className="ml-2 text-sm font-normal text-slate-500">
                        {current.items.length} {current.items.length === 1 ? 'ejercicio' : 'ejercicios'}
                    </span>
                )}
            </h2>

            {current.items.length === 0 ? (
                <Card className="flex flex-col items-center gap-2 py-10 text-center">
                    <BedDouble className="size-10 text-slate-300" aria-hidden="true" />
                    <p className="font-semibold">{day === today ? 'Hoy es día de descanso' : 'Día de descanso'}</p>
                    <p className="text-sm text-slate-500">Recuperarse también es parte del entrenamiento.</p>
                </Card>
            ) : (
                <div className="space-y-4">
                    {current.items.map((item, i) => (
                        <RoutineExerciseCard key={item.id} item={item} index={i} />
                    ))}
                </div>
            )}

            {routine.notes && (
                <Card>
                    <h2 className="text-sm font-semibold text-slate-700">Notas del entrenador</h2>
                    <p className="mt-1 text-sm whitespace-pre-line text-slate-600">{routine.notes}</p>
                </Card>
            )}
        </AppLayout>
    );
}
