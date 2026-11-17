import { router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { ArrowDown, ArrowLeft, ArrowUp, Plus, Save, Search, Trash2, UserMinus, Video, X } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, Button, Card, cx, Field, Input, Select, Textarea } from '@/Components/ui';
import { formatDate } from '@/lib/format';
import DayTabs from '@/Components/Training/DayTabs';
import { DAYS } from '@/Components/Training/days';

let seq = 0;
const newKey = () => `n${++seq}`;

const emptyWeek = () => Object.fromEntries(DAYS.map(({ day }) => [day, []]));

function weekFromItems(items) {
    const week = emptyWeek();
    for (const item of items) {
        week[item.day_of_week].push({
            key: `i${item.id}`,
            exercise_id: item.exercise.id,
            sets: item.sets,
            reps: item.reps,
            weight_kg: item.weight_kg ?? '',
            rest_seconds: item.rest_seconds ?? '',
        });
    }
    return week;
}

/** Constructor de la rutina: pestañas por día, catálogo, orden y asignación. */
export default function RoutineEdit({ routine, catalog, muscleGroups, assignments, members, today }) {
    const [meta, setMeta] = useState({ name: routine.name, goal: routine.goal ?? '', notes: routine.notes ?? '' });
    const [week, setWeek] = useState(() => weekFromItems(routine.items));
    const [day, setDay] = useState(() => DAYS.find(({ day }) => routine.items.some((i) => i.day_of_week === day))?.day ?? 1);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState({});

    const byId = useMemo(() => Object.fromEntries(catalog.map((e) => [e.id, e])), [catalog]);
    const counts = Object.fromEntries(DAYS.map(({ day }) => [day, week[day].length]));
    const dayName = DAYS.find((d) => d.day === day).name;

    useEffect(() => {
        if (!dirty) return;
        const warn = (e) => {
            e.preventDefault();
            e.returnValue = '';
        };
        window.addEventListener('beforeunload', warn);
        return () => window.removeEventListener('beforeunload', warn);
    }, [dirty]);

    const updateDay = (fn) => {
        setWeek((w) => ({ ...w, [day]: fn(w[day]) }));
        setDirty(true);
    };
    const addExercise = (exercise) =>
        updateDay((items) => [...items, { key: newKey(), exercise_id: exercise.id, sets: 3, reps: '10-12', weight_kg: '', rest_seconds: 60 }]);
    const changeItem = (key, field, value) => updateDay((items) => items.map((i) => (i.key === key ? { ...i, [field]: value } : i)));
    const removeItem = (key) => updateDay((items) => items.filter((i) => i.key !== key));
    const moveItem = (index, delta) =>
        updateDay((items) => {
            const next = [...items];
            const target = index + delta;
            if (target < 0 || target >= next.length) return items;
            [next[index], next[target]] = [next[target], next[index]];
            return next;
        });
    const changeMeta = (field, value) => {
        setMeta((m) => ({ ...m, [field]: value }));
        setDirty(true);
    };

    // Toda la semana en una sola petición; los errores items.N.campo se
    // regresan al renglón correspondiente.
    const save = () => {
        const flat = DAYS.flatMap(({ day }) => week[day].map((item) => ({ day, item })));
        const payload = {
            ...meta,
            items: flat.map(({ day, item }) => ({
                day_of_week: day,
                exercise_id: item.exercise_id,
                sets: Number(item.sets) || 0,
                reps: String(item.reps ?? '').trim(),
                weight_kg: item.weight_kg === '' ? null : Number(item.weight_kg),
                rest_seconds: item.rest_seconds === '' ? null : Number(item.rest_seconds),
            })),
        };

        router.put(`/panel/rutinas/${routine.id}`, payload, {
            preserveScroll: true,
            preserveState: true,
            onStart: () => setSaving(true),
            onFinish: () => setSaving(false),
            onSuccess: () => {
                setErrors({});
                setDirty(false);
            },
            onError: (errs) => {
                const mapped = { meta: {}, items: {} };
                for (const [key, message] of Object.entries(errs)) {
                    const m = key.match(/^items\.(\d+)\.(\w+)$/);
                    if (m && flat[m[1]]) {
                        const k = flat[m[1]].item.key;
                        mapped.items[k] = { ...mapped.items[k], [m[2]]: message };
                    } else {
                        mapped.meta[key] = message;
                    }
                }
                setErrors(mapped);
                const firstBad = flat.find(({ item }) => mapped.items[item.key]);
                if (firstBad) setDay(firstBad.day);
            },
        });
    };

    const destroy = () => {
        if (confirm(`¿Eliminar la rutina «${routine.name}»? Los socios que la tienen se quedarán sin rutina.`)) {
            router.delete(`/panel/rutinas/${routine.id}`);
        }
    };

    const items = week[day];

    return (
        <AppLayout title={routine.name}>
            <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                <Button href="/panel/rutinas" variant="ghost" size="sm">
                    <ArrowLeft className="size-4" aria-hidden="true" /> Rutinas
                </Button>
                <Button variant="ghost" size="sm" onClick={destroy} className="text-red-600">
                    <Trash2 className="size-4" aria-hidden="true" /> Eliminar rutina
                </Button>
            </div>

            <Card className="mb-5">
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Nombre" htmlFor="name" error={errors.meta?.name}>
                        <Input id="name" value={meta.name} onChange={(e) => changeMeta('name', e.target.value)} maxLength={100} />
                    </Field>
                    <Field label="Objetivo" htmlFor="goal" error={errors.meta?.goal}>
                        <Input id="goal" value={meta.goal} onChange={(e) => changeMeta('goal', e.target.value)} maxLength={120} placeholder="Opcional" />
                    </Field>
                    <Field label="Notas para el socio" htmlFor="notes" error={errors.meta?.notes} className="sm:col-span-2">
                        <Textarea id="notes" value={meta.notes} onChange={(e) => changeMeta('notes', e.target.value)} maxLength={2000} className="min-h-16" placeholder="Opcional" />
                    </Field>
                </div>
                {errors.meta?.items && <p className="mt-3 text-sm text-red-600">{errors.meta.items}</p>}
            </Card>

            <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <section className="min-w-0 space-y-4" aria-label="Ejercicios por día">
                    <DayTabs value={day} onChange={setDay} counts={counts} />
                    <h2 className="text-lg font-semibold">
                        {dayName}
                        <span className="ml-2 text-sm font-normal text-slate-500">{items.length === 0 ? 'Descanso' : `${items.length} ejercicios`}</span>
                    </h2>

                    {items.length === 0 ? (
                        <p className="rounded-2xl border-2 border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500">
                            Día de descanso. Agrega ejercicios desde el catálogo.
                        </p>
                    ) : (
                        <ol className="space-y-3">
                            {items.map((item, index) => (
                                <ItemRow
                                    key={item.key}
                                    item={item}
                                    index={index}
                                    total={items.length}
                                    exercise={byId[item.exercise_id]}
                                    errors={errors.items?.[item.key]}
                                    onChange={(field, value) => changeItem(item.key, field, value)}
                                    onMove={(delta) => moveItem(index, delta)}
                                    onRemove={() => removeItem(item.key)}
                                />
                            ))}
                        </ol>
                    )}
                </section>

                <CatalogPicker catalog={catalog} muscleGroups={muscleGroups} dayName={dayName} onAdd={addExercise} />
            </div>

            {/* Barra de guardado fija para no perderla en móvil */}
            <div className="sticky bottom-0 z-10 -mx-4 mt-5 border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur sm:mx-0 sm:rounded-2xl sm:border">
                <div className="flex items-center justify-between gap-3">
                    <p className="text-sm text-slate-500">{dirty ? 'Tienes cambios sin guardar.' : 'Todo guardado.'}</p>
                    <Button onClick={save} disabled={saving || !dirty}>
                        <Save className="size-4" aria-hidden="true" /> {saving ? 'Guardando…' : 'Guardar semana'}
                    </Button>
                </div>
            </div>

            <AssignPanel routineId={routine.id} assignments={assignments} members={members} today={today} />
        </AppLayout>
    );
}

function ItemRow({ item, index, total, exercise, errors = {}, onChange, onMove, onRemove }) {
    const id = (f) => `${item.key}-${f}`;
    return (
        <li>
            <Card className={cx('space-y-3', Object.keys(errors).length && 'ring-red-300')}>
                <div className="flex items-start gap-2">
                    <span className="grid size-7 shrink-0 place-items-center rounded-full bg-slate-100 text-xs font-bold">{index + 1}</span>
                    <div className="min-w-0 flex-1">
                        <p className="truncate font-semibold">{exercise?.name ?? 'Ejercicio eliminado'}</p>
                        <p className="text-xs text-slate-500">{exercise?.muscle_group_label}</p>
                    </div>
                    <div className="flex shrink-0 gap-0.5">
                        <IconButton label="Subir" onClick={() => onMove(-1)} disabled={index === 0}>
                            <ArrowUp className="size-4" />
                        </IconButton>
                        <IconButton label="Bajar" onClick={() => onMove(1)} disabled={index === total - 1}>
                            <ArrowDown className="size-4" />
                        </IconButton>
                        <IconButton label="Quitar" onClick={onRemove} className="text-red-600">
                            <X className="size-4" />
                        </IconButton>
                    </div>
                </div>
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <Field label="Series" htmlFor={id('sets')} error={errors.sets}>
                        <Input id={id('sets')} type="number" inputMode="numeric" min={1} max={20} value={item.sets} onChange={(e) => onChange('sets', e.target.value)} />
                    </Field>
                    <Field label="Reps" htmlFor={id('reps')} error={errors.reps}>
                        <Input id={id('reps')} value={item.reps} onChange={(e) => onChange('reps', e.target.value)} maxLength={20} placeholder="10-12" />
                    </Field>
                    <Field label="Peso (kg)" htmlFor={id('weight')} error={errors.weight_kg}>
                        <Input
                            id={id('weight')}
                            type="number"
                            inputMode="decimal"
                            min={0}
                            step="0.5"
                            value={item.weight_kg}
                            onChange={(e) => onChange('weight_kg', e.target.value)}
                            placeholder="—"
                        />
                    </Field>
                    <Field label="Descanso (s)" htmlFor={id('rest')} error={errors.rest_seconds}>
                        <Input
                            id={id('rest')}
                            type="number"
                            inputMode="numeric"
                            min={0}
                            max={900}
                            step={15}
                            value={item.rest_seconds}
                            onChange={(e) => onChange('rest_seconds', e.target.value)}
                            placeholder="—"
                        />
                    </Field>
                </div>
                {errors.exercise_id && <p className="text-sm text-red-600">{errors.exercise_id}</p>}
            </Card>
        </li>
    );
}

function IconButton({ label, className, children, ...props }) {
    return (
        <button
            type="button"
            aria-label={label}
            title={label}
            className={cx('grid size-9 place-items-center rounded-lg text-slate-600 hover:bg-slate-100 disabled:opacity-30', className)}
            {...props}
        >
            {children}
        </button>
    );
}

function CatalogPicker({ catalog, muscleGroups, dayName, onAdd }) {
    const [term, setTerm] = useState('');
    const [group, setGroup] = useState('');

    const results = useMemo(() => {
        const t = term.trim().toLowerCase();
        return catalog.filter((e) => (!group || e.muscle_group === group) && (!t || e.name.toLowerCase().includes(t)));
    }, [catalog, term, group]);

    return (
        <aside aria-label="Catálogo de ejercicios" className="lg:sticky lg:top-4 lg:self-start">
            <Card className="space-y-3">
                <h2 className="font-semibold">Agregar a {dayName}</h2>
                <div className="relative">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                    <Input type="search" value={term} onChange={(e) => setTerm(e.target.value)} placeholder="Buscar ejercicio" aria-label="Buscar ejercicio" className="pl-9" />
                </div>
                <Select value={group} onChange={(e) => setGroup(e.target.value)} aria-label="Grupo muscular">
                    <option value="">Todos los grupos</option>
                    {muscleGroups.map((g) => (
                        <option key={g.value} value={g.value}>
                            {g.label}
                        </option>
                    ))}
                </Select>

                {catalog.length === 0 ? (
                    <p className="text-sm text-slate-500">
                        No hay ejercicios en el catálogo.{' '}
                        <a href="/panel/ejercicios" className="font-semibold underline">
                            Agrega algunos
                        </a>
                        .
                    </p>
                ) : (
                    <ul className="-mx-2 max-h-80 divide-y divide-slate-100 overflow-y-auto">
                        {results.map((e) => (
                            <li key={e.id}>
                                <button
                                    type="button"
                                    onClick={() => onAdd(e)}
                                    className="flex w-full items-center gap-3 rounded-lg px-2 py-2.5 text-left hover:bg-slate-50"
                                >
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-sm font-medium">{e.name}</span>
                                        <span className="flex items-center gap-1 text-xs text-slate-500">
                                            {e.muscle_group_label}
                                            {e.clip_url && <Video className="size-3.5" aria-label="Con clip" />}
                                        </span>
                                    </span>
                                    <Plus className="size-5 shrink-0 text-slate-400" aria-hidden="true" />
                                </button>
                            </li>
                        ))}
                        {results.length === 0 && <li className="px-2 py-3 text-sm text-slate-500">Sin resultados.</li>}
                    </ul>
                )}
            </Card>
        </aside>
    );
}

function AssignPanel({ routineId, assignments, members, today }) {
    const form = useForm({ user_ids: [], starts_on: today });
    const [term, setTerm] = useState('');
    const candidates = useMemo(() => {
        const assigned = new Set(assignments.map((a) => a.user.id));
        const t = term.trim().toLowerCase();
        return members.filter((m) => !assigned.has(m.id) && (!t || m.name.toLowerCase().includes(t) || m.email.toLowerCase().includes(t)));
    }, [members, term, assignments]);

    const toggle = (id) =>
        form.setData('user_ids', form.data.user_ids.includes(id) ? form.data.user_ids.filter((x) => x !== id) : [...form.data.user_ids, id]);

    const submit = (e) => {
        e.preventDefault();
        form.post(`/panel/rutinas/${routineId}/asignaciones`, { preserveScroll: true, onSuccess: () => form.reset('user_ids') });
    };

    const unassign = (a) => {
        if (confirm(`¿Quitar esta rutina a ${a.user.name}?`)) {
            router.delete(`/panel/rutinas/${routineId}/asignaciones/${a.id}`, { preserveScroll: true });
        }
    };

    const memberError = Object.entries(form.errors).find(([k]) => k.startsWith('user_ids'))?.[1];
    const n = form.data.user_ids.length;

    return (
        <div className="mt-8 grid gap-5 lg:grid-cols-2">
            <Card>
                <h2 className="font-semibold">Socios con esta rutina</h2>
                {assignments.length === 0 ? (
                    <p className="mt-2 text-sm text-slate-500">Todavía nadie la tiene asignada.</p>
                ) : (
                    <ul className="mt-3 divide-y divide-slate-100">
                        {assignments.map((a) => (
                            <li key={a.id} className="flex items-center gap-3 py-2.5">
                                <div className="min-w-0 flex-1">
                                    <p className="truncate text-sm font-medium">{a.user.name}</p>
                                    <p className="truncate text-xs text-slate-500">Desde {formatDate(a.starts_on)}</p>
                                </div>
                                <IconButton label={`Quitar a ${a.user.name}`} onClick={() => unassign(a)} className="text-red-600">
                                    <UserMinus className="size-4" />
                                </IconButton>
                            </li>
                        ))}
                    </ul>
                )}
            </Card>

            <Card>
                <form onSubmit={submit} className="space-y-3">
                    <h2 className="font-semibold">Asignar a socios</h2>
                    <p className="text-sm text-slate-500">Si el socio ya tiene otra rutina, se reemplaza por esta.</p>
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                        <Input type="search" value={term} onChange={(e) => setTerm(e.target.value)} placeholder="Buscar socio" aria-label="Buscar socio" className="pl-9" />
                    </div>
                    <ul className="-mx-2 max-h-72 overflow-y-auto">
                        {candidates.map((m) => (
                            <li key={m.id}>
                                <label className="flex cursor-pointer items-center gap-3 rounded-lg px-2 py-2 hover:bg-slate-50">
                                    <input type="checkbox" className="size-4" checked={form.data.user_ids.includes(m.id)} onChange={() => toggle(m.id)} />
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-sm font-medium">{m.name}</span>
                                        <span className="block truncate text-xs text-slate-500">{m.email}</span>
                                    </span>
                                    {m.current_routine && <Badge tone="amber">Tiene: {m.current_routine}</Badge>}
                                </label>
                            </li>
                        ))}
                        {candidates.length === 0 && <li className="px-2 py-3 text-sm text-slate-500">No hay socios para mostrar.</li>}
                    </ul>
                    <Field label="Empieza el" htmlFor="starts_on" error={form.errors.starts_on}>
                        <Input id="starts_on" type="date" value={form.data.starts_on} onChange={(e) => form.setData('starts_on', e.target.value)} />
                    </Field>
                    {memberError && <p className="text-sm text-red-600">{memberError}</p>}
                    <Button type="submit" disabled={form.processing || n === 0} className="w-full">
                        {n === 0 ? 'Elige socios' : n === 1 ? 'Asignar a 1 socio' : `Asignar a ${n} socios`}
                    </Button>
                </form>
            </Card>
        </div>
    );
}
