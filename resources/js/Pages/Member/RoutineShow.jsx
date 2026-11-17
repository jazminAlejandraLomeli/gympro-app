import { router } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, BedDouble, Pencil, RotateCcw, Trash2 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, Button, Card } from '@/Components/ui';
import DayTabs from '@/Components/Training/DayTabs';
import RoutineExerciseCard from '@/Components/Training/RoutineExerciseCard';
import { authorLabel } from '@/Components/Training/days';

/** Una rutina del socio en modo lectura (vigente o del historial). */
export default function RoutineShow({ routine, items, days, day: today }) {
    const isSingle = routine.type === 'single';
    const [day, setDay] = useState(today);
    const current = days.find((d) => d.day === day);
    const list = isSingle ? items : (current?.items ?? []);

    const reuse = () => router.post(`/app/rutina/${routine.id}/usar`);
    const destroy = () => {
        if (confirm(`¿Borrar «${routine.name}»? Se quitará de tu historial.`)) {
            router.delete(`/app/rutina/${routine.id}`);
        }
    };

    return (
        <AppLayout title={routine.name}>
            <div>
                <Button href="/app/rutina/historial" variant="ghost" size="sm" className="-ml-2">
                    <ArrowLeft className="size-4" aria-hidden="true" /> Mi historial
                </Button>
                <h1 className="mt-1 text-2xl leading-tight font-bold">{routine.name}</h1>
                <p className="mt-1 flex flex-wrap items-center gap-2 text-sm text-slate-600">
                    <Badge tone={isSingle ? 'amber' : 'slate'}>{routine.type_label}</Badge>
                    {routine.is_current && <Badge tone="green">Vigente</Badge>}
                    <span>{authorLabel(routine.author)}</span>
                </p>
                {routine.goal && <p className="mt-1 text-sm text-slate-500">{routine.goal}</p>}
            </div>

            <div className="grid gap-2">
                <Button size="lg" onClick={reuse} className="text-base">
                    <RotateCcw className="size-5" aria-hidden="true" /> {isSingle ? 'Usarla hoy' : 'Volver a usar esta semana'}
                </Button>
                {routine.can_edit && (
                    <div className="grid grid-cols-2 gap-2">
                        <Button href={`/app/rutina/${routine.id}/editar`} variant="secondary" className="min-h-12">
                            <Pencil className="size-4" aria-hidden="true" /> Editar
                        </Button>
                        <Button variant="ghost" onClick={destroy} className="min-h-12 text-red-600">
                            <Trash2 className="size-4" aria-hidden="true" /> Borrar
                        </Button>
                    </div>
                )}
            </div>

            {!isSingle && <DayTabs value={day} onChange={setDay} counts={Object.fromEntries(days.map((d) => [d.day, d.items.length]))} />}

            {!isSingle && <h2 className="text-lg font-semibold">{current?.name}</h2>}

            {list.length === 0 ? (
                <Card className="flex flex-col items-center gap-2 py-10 text-center">
                    <BedDouble className="size-10 text-slate-300" aria-hidden="true" />
                    <p className="font-semibold">{isSingle ? 'Esta rutina no tiene ejercicios' : 'Día de descanso'}</p>
                </Card>
            ) : (
                <div className="space-y-4">
                    {list.map((item, i) => (
                        <RoutineExerciseCard key={item.id} item={item} index={i} />
                    ))}
                </div>
            )}

            {routine.notes && (
                <Card>
                    <h2 className="text-sm font-semibold text-slate-700">{routine.author.isSelf ? 'Tus notas' : 'Notas del coach'}</h2>
                    <p className="mt-1 text-sm whitespace-pre-line text-slate-600">{routine.notes}</p>
                </Card>
            )}
        </AppLayout>
    );
}
