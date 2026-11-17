import { Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { BedDouble, CalendarDays, CalendarRange, ChevronRight, Clock3, History, MessageCircleQuestion, PencilRuler, X } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, Button, Card, cx, Field, Textarea } from '@/Components/ui';
import { formatDate } from '@/lib/format';
import DayTabs from '@/Components/Training/DayTabs';
import RoutineExerciseCard from '@/Components/Training/RoutineExerciseCard';
import { authorLabel, formatWeight, timeAgo } from '@/Components/Training/days';

/**
 * "Mi rutina": arriba la rutina de HOY (la de un día programada para hoy
 * o, si no hay, el día de hoy de la semanal), luego las acciones y la
 * semana completa.
 */
export default function Routine({ day, dayName, todayDate, today, weekly, pendingRequest, hasHistory }) {
    return (
        <AppLayout title="Mi rutina">
            <h1 className="text-2xl font-bold">Mi rutina</h1>

            <TodaySection today={today} dayName={dayName} />

            <Actions pendingRequest={pendingRequest} hasHistory={hasHistory} />

            {weekly && <WeekSection weekly={weekly} day={day} todayDate={todayDate} />}
        </AppLayout>
    );
}

function TodaySection({ today, dayName }) {
    if (!today) {
        return (
            <Card className="text-center">
                <BedDouble className="mx-auto size-10 text-slate-300" aria-hidden="true" />
                <h2 className="mt-2 font-semibold">Hoy no tienes rutina</h2>
                <p className="mt-1 text-sm text-slate-500">Pídele una a tu coach o arma la tuya en un minuto.</p>
            </Card>
        );
    }

    const { routine, items, isRestDay, source } = today;

    return (
        <section className="space-y-3" aria-labelledby="today-title">
            <Card className="space-y-1">
                <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase">
                    Tu rutina de hoy · {dayName}
                </p>
                <h2 id="today-title" className="text-xl leading-tight font-bold">
                    {routine.name}
                </h2>
                <p className="flex flex-wrap items-center gap-2 text-sm text-slate-600">
                    <Badge tone={source === 'single' ? 'primary' : 'slate'}>{source === 'single' ? 'Sesión de hoy' : 'De tu semana'}</Badge>
                    <span>{authorLabel(routine.author)}</span>
                </p>
                {routine.goal && <p className="text-sm text-slate-500">{routine.goal}</p>}
                <Link href={`/app/rutina/${routine.id}`} className="inline-flex items-center gap-1 pt-1 text-sm font-semibold text-slate-700">
                    Ver detalle <ChevronRight className="size-4" aria-hidden="true" />
                </Link>
            </Card>

            {isRestDay ? (
                <Card className="flex flex-col items-center gap-2 py-8 text-center">
                    <BedDouble className="size-10 text-slate-300" aria-hidden="true" />
                    <p className="font-semibold">Hoy es día de descanso</p>
                    <p className="text-sm text-slate-500">Recuperarse también es parte del entrenamiento.</p>
                </Card>
            ) : (
                <div className="space-y-4">
                    {items.map((item, i) => (
                        <RoutineExerciseCard key={item.id} item={item} index={i} />
                    ))}
                </div>
            )}

            {routine.notes && (
                <Card>
                    <h3 className="text-sm font-semibold text-slate-700">{routine.author.isSelf ? 'Tus notas' : 'Notas del coach'}</h3>
                    <p className="mt-1 text-sm whitespace-pre-line text-slate-600">{routine.notes}</p>
                </Card>
            )}
        </section>
    );
}

function Actions({ pendingRequest, hasHistory }) {
    const [asking, setAsking] = useState(false);

    return (
        <section className="space-y-3" aria-label="Acciones">
            {pendingRequest ? (
                <PendingRequestCard request={pendingRequest} />
            ) : asking ? (
                <RequestForm onClose={() => setAsking(false)} />
            ) : (
                <Button size="lg" onClick={() => setAsking(true)} className="text-base">
                    <MessageCircleQuestion className="size-5" aria-hidden="true" /> Pedir rutina a un coach
                </Button>
            )}
            <div className="grid grid-cols-2 gap-2">
                <Button href="/app/rutina/nueva" variant="secondary" className="min-h-14 flex-col gap-1 py-3 text-sm">
                    <PencilRuler className="size-5" aria-hidden="true" /> Crear mi rutina
                </Button>
                <Button href="/app/rutina/historial" variant="secondary" className="min-h-14 flex-col gap-1 py-3 text-sm">
                    <History className="size-5" aria-hidden="true" /> Mi historial
                </Button>
            </div>
        </section>
    );
}

function PendingRequestCard({ request }) {
    const cancel = () => {
        if (confirm('¿Cancelar tu solicitud de rutina?')) {
            router.delete(`/app/rutina/solicitudes/${request.id}`, { preserveScroll: true });
        }
    };

    return (
        <Card className="space-y-2 ring-amber-200">
            <div className="flex items-start gap-3">
                <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-amber-100 text-amber-800">
                    <Clock3 className="size-5" aria-hidden="true" />
                </span>
                <div className="min-w-0">
                    <p className="font-semibold">Tu solicitud está en espera</p>
                    <p className="text-xs text-slate-500">
                        La enviaste {timeAgo(request.created_at)} · {request.kind === 'single' ? 'Rutina de un día' : 'Rutina semanal'}
                        {request.days_per_week ? ` · ${request.days_per_week} días` : ''}
                    </p>
                </div>
            </div>
            <p className="rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-700">“{request.goal}”</p>
            <p className="text-sm text-slate-500">Un coach te la armará y aparecerá aquí.</p>
            <Button variant="ghost" onClick={cancel} className="w-full text-red-600">
                <X className="size-4" aria-hidden="true" /> Cancelar solicitud
            </Button>
        </Card>
    );
}

function RequestForm({ onClose }) {
    const form = useForm({ kind: 'weekly', goal: '', days_per_week: 3 });

    const submit = (e) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, days_per_week: data.kind === 'weekly' ? data.days_per_week : null }));
        form.post('/app/rutina/solicitudes', { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Card>
            <form onSubmit={submit} className="space-y-4">
                <div className="flex items-start justify-between gap-2">
                    <h2 className="text-lg font-semibold">Pedir rutina a un coach</h2>
                    <button type="button" onClick={onClose} className="grid size-10 place-items-center rounded-lg text-slate-500 hover:bg-slate-100" aria-label="Cerrar">
                        <X className="size-5" aria-hidden="true" />
                    </button>
                </div>

                <fieldset>
                    <legend className="mb-1.5 block text-sm font-medium text-slate-700">¿Qué necesitas?</legend>
                    <div className="grid grid-cols-2 gap-2">
                        <KindButton active={form.data.kind === 'single'} icon={CalendarDays} onClick={() => form.setData('kind', 'single')}>
                            Para hoy
                        </KindButton>
                        <KindButton active={form.data.kind === 'weekly'} icon={CalendarRange} onClick={() => form.setData('kind', 'weekly')}>
                            Para la semana
                        </KindButton>
                    </div>
                    {form.errors.kind && <p className="mt-1 text-sm text-red-600">{form.errors.kind}</p>}
                </fieldset>

                <Field label="¿Qué quieres lograr?" htmlFor="rq-goal" error={form.errors.goal}>
                    <Textarea
                        id="rq-goal"
                        value={form.data.goal}
                        onChange={(e) => form.setData('goal', e.target.value)}
                        maxLength={500}
                        placeholder="Ej. Bajar de peso y tonificar piernas. Tengo una lesión en la rodilla."
                    />
                </Field>

                {form.data.kind === 'weekly' && (
                    <fieldset>
                        <legend className="mb-1.5 block text-sm font-medium text-slate-700">¿Cuántos días por semana puedes venir?</legend>
                        <div className="grid grid-cols-7 gap-1">
                            {[1, 2, 3, 4, 5, 6, 7].map((n) => (
                                <button
                                    key={n}
                                    type="button"
                                    aria-pressed={form.data.days_per_week === n}
                                    onClick={() => form.setData('days_per_week', n)}
                                    className={cx(
                                        'min-h-11 rounded-xl text-sm font-bold',
                                        form.data.days_per_week === n ? 'bg-primary text-on-primary' : 'bg-slate-100 text-slate-700',
                                    )}
                                >
                                    {n}
                                </button>
                            ))}
                        </div>
                        {form.errors.days_per_week && <p className="mt-1 text-sm text-red-600">{form.errors.days_per_week}</p>}
                    </fieldset>
                )}

                {form.errors.request && <p className="text-sm text-red-600">{form.errors.request}</p>}

                <Button type="submit" size="lg" disabled={form.processing}>
                    {form.processing ? 'Enviando…' : 'Enviar a mi coach'}
                </Button>
            </form>
        </Card>
    );
}

function KindButton({ active, icon: IconCmp, children, ...props }) {
    return (
        <button
            type="button"
            aria-pressed={active}
            className={cx(
                'flex min-h-16 flex-col items-center justify-center gap-1 rounded-2xl text-sm font-semibold',
                active ? 'bg-primary text-on-primary' : 'bg-white text-slate-800 ring-1 ring-slate-300',
            )}
            {...props}
        >
            <IconCmp className="size-5" aria-hidden="true" />
            {children}
        </button>
    );
}

function WeekSection({ weekly, day: today, todayDate }) {
    const [day, setDay] = useState(today);
    const { routine, days } = weekly;
    const current = days.find((d) => d.day === day) ?? days[0];
    const counts = Object.fromEntries(days.map((d) => [d.day, d.items.length]));
    const startsLater = routine.starts_on > todayDate;

    return (
        <section className="space-y-3" aria-labelledby="week-title">
            <div className="flex items-end justify-between gap-3 pt-2">
                <div className="min-w-0">
                    <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase">Tu semana</p>
                    <h2 id="week-title" className="truncate text-lg font-bold">
                        {routine.name}
                    </h2>
                    <p className="text-sm text-slate-500">{authorLabel(routine.author)}</p>
                    {startsLater && <p className="text-sm font-medium text-amber-700">Empieza el {formatDate(routine.starts_on)}</p>}
                </div>
                <Link href={`/app/rutina/${routine.id}`} className="shrink-0 text-sm font-semibold text-slate-700">
                    Ver con videos
                </Link>
            </div>

            <DayTabs value={day} onChange={setDay} counts={counts} today={today} />

            <Card className="p-0 sm:p-0">
                <h3 className="border-b border-slate-100 px-4 py-3 font-semibold">
                    {day === today ? `Hoy · ${current.name}` : current.name}
                    <span className="ml-2 text-sm font-normal text-slate-500">
                        {current.items.length === 0 ? 'Descanso' : `${current.items.length} ${current.items.length === 1 ? 'ejercicio' : 'ejercicios'}`}
                    </span>
                </h3>
                {current.items.length === 0 ? (
                    <p className="px-4 py-6 text-center text-sm text-slate-500">Día de descanso.</p>
                ) : (
                    <ol className="divide-y divide-slate-100">
                        {current.items.map((item, i) => (
                            <li key={item.id} className="flex items-center gap-3 px-4 py-3">
                                <span className="grid size-7 shrink-0 place-items-center rounded-full bg-slate-100 text-xs font-bold">{i + 1}</span>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-semibold">{item.exercise.name}</p>
                                    <p className="truncate text-xs text-slate-500">{item.notes || item.exercise.muscle_group_label}</p>
                                </div>
                                <p className="shrink-0 text-right text-sm font-semibold">
                                    {item.sets} × {item.reps}
                                    {formatWeight(item.weight_kg) && <span className="block text-xs font-normal text-slate-500">{formatWeight(item.weight_kg)}</span>}
                                </p>
                            </li>
                        ))}
                    </ol>
                )}
            </Card>
        </section>
    );
}
