import { Clock, MessageSquareText, Repeat, Weight } from 'lucide-react';
import { Card } from '@/Components/ui';
import ExerciseClip from './ExerciseClip';
import { formatRest, formatWeight } from './days';

/** Tarjeta de un ejercicio de la rutina (vista del socio). */
export default function RoutineExerciseCard({ item, index }) {
    const { exercise } = item;
    const weight = formatWeight(item.weight_kg);
    const rest = formatRest(item.rest_seconds);

    return (
        <Card className="overflow-hidden p-0 sm:p-0">
            <ExerciseClip src={exercise.clip_url} title={exercise.name} className="aspect-video w-full" />
            <div className="space-y-3 p-4">
                <div>
                    <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase">
                        {index + 1}. {exercise.muscle_group_label}
                        {exercise.equipment_label && ` · ${exercise.equipment_label}`}
                    </p>
                    <h3 className="text-lg leading-tight font-bold">{exercise.name}</h3>
                    {exercise.objective && <p className="text-sm text-slate-500">{exercise.objective}</p>}
                </div>
                <dl className="grid grid-cols-3 gap-2 text-center">
                    <div className="rounded-xl bg-slate-100 px-2 py-2">
                        <dt className="flex items-center justify-center gap-1 text-[11px] text-slate-500">
                            <Repeat className="size-3.5" aria-hidden="true" /> Series
                        </dt>
                        <dd className="font-bold break-words">
                            {item.sets} × {item.reps}
                        </dd>
                    </div>
                    <div className="rounded-xl bg-slate-100 px-2 py-2">
                        <dt className="flex items-center justify-center gap-1 text-[11px] text-slate-500">
                            <Weight className="size-3.5" aria-hidden="true" /> Peso
                        </dt>
                        <dd className="font-bold">{weight ?? '—'}</dd>
                    </div>
                    <div className="rounded-xl bg-slate-100 px-2 py-2">
                        <dt className="flex items-center justify-center gap-1 text-[11px] text-slate-500">
                            <Clock className="size-3.5" aria-hidden="true" /> Descanso
                        </dt>
                        <dd className="font-bold">{rest ?? '—'}</dd>
                    </div>
                </dl>
                {item.notes && (
                    <p className="flex gap-2 rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-900">
                        <MessageSquareText className="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                        <span>{item.notes}</span>
                    </p>
                )}
                {exercise.instructions && (
                    <details className="text-sm text-slate-600">
                        <summary className="cursor-pointer font-medium text-slate-700">Cómo se hace</summary>
                        <p className="mt-1 whitespace-pre-line">{exercise.instructions}</p>
                        {exercise.notes && <p className="mt-2 whitespace-pre-line text-slate-500">{exercise.notes}</p>}
                    </details>
                )}
            </div>
        </Card>
    );
}
