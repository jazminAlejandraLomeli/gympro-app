import { router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { ArrowDown, ArrowUp, CalendarDays, CalendarRange, Copy, Dumbbell, LayoutTemplate, ListPlus, Save, Trash2, UserRound } from 'lucide-react';
import { Button, Card, cx, Field, Input, Select, Textarea } from '@/Components/ui';
import DayTabs from './DayTabs';
import { DAYS } from './days';
import ExercisePicker from './ExercisePicker';
import { MemberSelect } from './MemberPicker';

/*
 * Constructor de rutinas compartido por el panel (coach y dueño) y el
 * portal del socio.
 *
 *   <RoutineBuilder
 *       audience="panel" | "member"
 *       initial={{ name, type, goal, notes, items, target?, member_id?, scheduled_for?, request_id? }}
 *       submitUrl="/panel/rutinas" method="post" | "put"
 *       catalog muscleGroups members? lockType? lockMember? today
 *   />
 *
 * Todo se guarda en UNA petición; los errores items.N.campo regresan al
 * renglón correspondiente (y se abre su día).
 */

let seq = 0;
const newKey = () => `n${++seq}`;
const SINGLE = 0; // las de un día viven en la lista 0

const emptyLists = () => Object.fromEntries([SINGLE, ...DAYS.map((d) => d.day)].map((k) => [k, []]));

const toRow = (exercise, extra = {}) => ({
    key: newKey(),
    exercise_id: exercise.id,
    sets: 3,
    reps: '10-12',
    weight_kg: '',
    rest_seconds: 60,
    notes: '',
    ...extra,
});

function listsFromItems(items, type) {
    const lists = emptyLists();
    for (const item of items) {
        const slot = type === 'single' ? SINGLE : (item.day_of_week ?? 1);
        lists[slot].push({
            key: `i${item.id}`,
            exercise_id: item.exercise.id,
            sets: item.sets,
            reps: item.reps,
            weight_kg: item.weight_kg ?? '',
            rest_seconds: item.rest_seconds ?? '',
            notes: item.notes ?? '',
        });
    }
    return lists;
}

export default function RoutineBuilder({
    audience = 'panel',
    initial,
    submitUrl,
    method = 'post',
    catalog,
    muscleGroups,
    members = [],
    lockType = false,
    lockMember = false,
    today,
    submitLabel,
}) {
    const isPanel = audience === 'panel';
    const isCreate = method === 'post';

    const [meta, setMeta] = useState({
        name: initial.name ?? '',
        type: initial.type ?? 'weekly',
        goal: initial.goal ?? '',
        notes: initial.notes ?? '',
    });
    const [target, setTarget] = useState(initial.target ?? 'template');
    const [memberId, setMemberId] = useState(initial.member_id ?? null);
    const [scheduledFor, setScheduledFor] = useState(initial.scheduled_for ?? today);
    const [lists, setLists] = useState(() => listsFromItems(initial.items ?? [], initial.type));
    const [day, setDay] = useState(() => DAYS.find(({ day }) => (initial.items ?? []).some((i) => i.day_of_week === day))?.day ?? 1);
    const [picker, setPicker] = useState(false);
    const [dirty, setDirty] = useState(false);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState({ meta: {}, items: {} });

    const isSingle = meta.type === 'single';
    const slot = isSingle ? SINGLE : day;
    const items = lists[slot];
    const byId = useMemo(() => Object.fromEntries(catalog.map((e) => [e.id, e])), [catalog]);
    const counts = Object.fromEntries(DAYS.map(({ day }) => [day, lists[day].length]));
    const dayName = DAYS.find((d) => d.day === day)?.name;

    useEffect(() => {
        if (!dirty) return;
        const warn = (e) => {
            e.preventDefault();
            e.returnValue = '';
        };
        window.addEventListener('beforeunload', warn);
        return () => window.removeEventListener('beforeunload', warn);
    }, [dirty]);

    const touch = () => setDirty(true);
    const changeMeta = (field, value) => {
        setMeta((m) => ({ ...m, [field]: value }));
        touch();
    };
    const updateList = (fn) => {
        setLists((l) => ({ ...l, [slot]: fn(l[slot]) }));
        touch();
    };
    const addExercises = (exercises) => updateList((rows) => [...rows, ...exercises.map((e) => toRow(e))]);
    const changeItem = (key, field, value) => updateList((rows) => rows.map((r) => (r.key === key ? { ...r, [field]: value } : r)));
    const removeItem = (key) => updateList((rows) => rows.filter((r) => r.key !== key));
    const moveItem = (index, delta) =>
        updateList((rows) => {
            const next = [...rows];
            const to = index + delta;
            if (to < 0 || to >= next.length) return rows;
            [next[index], next[to]] = [next[to], next[index]];
            return next;
        });
    const copyFrom = (fromDay) => {
        if (!fromDay) return;
        updateList((rows) => [...rows, ...lists[Number(fromDay)].map((r) => ({ ...r, key: newKey() }))]);
    };

    const save = () => {
        const flat = isSingle
            ? lists[SINGLE].map((item) => ({ slot: SINGLE, item }))
            : DAYS.flatMap(({ day }) => lists[day].map((item) => ({ slot: day, item })));

        const payload = {
            ...meta,
            items: flat.map(({ slot, item }) => ({
                day_of_week: slot === SINGLE ? null : slot,
                exercise_id: item.exercise_id,
                sets: Number(item.sets) || 0,
                reps: String(item.reps ?? '').trim(),
                weight_kg: item.weight_kg === '' || item.weight_kg === null ? null : Number(item.weight_kg),
                rest_seconds: item.rest_seconds === '' || item.rest_seconds === null ? null : Number(item.rest_seconds),
                notes: String(item.notes ?? '').trim() || null,
            })),
        };
        if (isPanel && isCreate) {
            Object.assign(payload, {
                target,
                member_id: target === 'member' ? memberId : null,
                scheduled_for: target === 'member' && isSingle ? scheduledFor : null,
                request_id: target === 'member' ? (initial.request_id ?? null) : null,
            });
        }

        router.visit(submitUrl, {
            method,
            data: payload,
            preserveScroll: true,
            preserveState: true,
            onStart: () => setSaving(true),
            onFinish: () => setSaving(false),
            onSuccess: () => {
                setErrors({ meta: {}, items: {} });
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
                if (firstBad && firstBad.slot !== SINGLE) setDay(firstBad.slot);
                if (Object.keys(mapped.meta).length) window.scrollTo({ top: 0, behavior: 'smooth' });
            },
        });
    };

    const total = isSingle ? lists[SINGLE].length : DAYS.reduce((n, { day }) => n + lists[day].length, 0);
    const label = submitLabel ?? (isCreate ? (isPanel && target === 'member' ? 'Guardar y asignar' : 'Guardar rutina') : 'Guardar cambios');
    let step = 0;

    return (
        <div className="space-y-5">
            {/* 1. Datos */}
            <Card className="space-y-4">
                <StepTitle n={++step}>Datos de la rutina</StepTitle>
                <Field label="Nombre" htmlFor="rb-name" error={errors.meta.name}>
                    <Input id="rb-name" value={meta.name} onChange={(e) => changeMeta('name', e.target.value)} maxLength={100} placeholder="Ej. Pierna y glúteo" />
                </Field>

                <fieldset>
                    <legend className="mb-1.5 block text-sm font-medium text-slate-700">¿Para cuándo es?</legend>
                    <div className="grid grid-cols-2 gap-2">
                        <TypeButton
                            active={isSingle}
                            disabled={lockType}
                            icon={CalendarDays}
                            title="Solo un día"
                            hint="Hoy haces esto"
                            onClick={() => changeMeta('type', 'single')}
                        />
                        <TypeButton
                            active={!isSingle}
                            disabled={lockType}
                            icon={CalendarRange}
                            title="Semana completa"
                            hint="De lunes a domingo"
                            onClick={() => changeMeta('type', 'weekly')}
                        />
                    </div>
                    {lockType && <p className="mt-1.5 text-xs text-slate-500">El tipo no se puede cambiar después de crearla.</p>}
                    {errors.meta.type && <p className="mt-1.5 text-sm text-red-600">{errors.meta.type}</p>}
                </fieldset>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Objetivo (opcional)" htmlFor="rb-goal" error={errors.meta.goal}>
                        <Input id="rb-goal" value={meta.goal} onChange={(e) => changeMeta('goal', e.target.value)} maxLength={120} placeholder="Ej. Ganar fuerza" />
                    </Field>
                    <Field label="Notas (opcional)" htmlFor="rb-notes" error={errors.meta.notes}>
                        <Textarea
                            id="rb-notes"
                            value={meta.notes}
                            onChange={(e) => changeMeta('notes', e.target.value)}
                            maxLength={2000}
                            className="min-h-11"
                            rows={2}
                            placeholder={isPanel ? 'Indicaciones generales para el socio' : 'Recordatorios para ti'}
                        />
                    </Field>
                </div>
            </Card>

            {/* 2. ¿Para quién? (solo panel, al crear) */}
            {isPanel && isCreate && (
                <Card className="space-y-4">
                    <StepTitle n={++step}>¿Para quién?</StepTitle>
                    {!lockMember && (
                        <div className="grid grid-cols-2 gap-2">
                            <TypeButton
                                active={target === 'member'}
                                icon={UserRound}
                                title="Un socio"
                                hint="Se le asigna al guardar"
                                onClick={() => {
                                    setTarget('member');
                                    touch();
                                }}
                            />
                            <TypeButton
                                active={target === 'template'}
                                icon={LayoutTemplate}
                                title="Plantilla del gimnasio"
                                hint="Para reutilizar"
                                onClick={() => {
                                    setTarget('template');
                                    touch();
                                }}
                            />
                        </div>
                    )}
                    {target === 'member' && (
                        <div className="space-y-4">
                            <Field label="Socio" error={errors.meta.member_id || errors.meta.request_id}>
                                <MemberSelect
                                    members={members}
                                    value={memberId}
                                    locked={lockMember}
                                    onChange={(id) => {
                                        setMemberId(id);
                                        touch();
                                    }}
                                />
                            </Field>
                            {isSingle && (
                                <Field label="Fecha de la sesión" htmlFor="rb-date" error={errors.meta.scheduled_for} hint="Ese día verá esta rutina como «Tu rutina de hoy».">
                                    <Input id="rb-date" type="date" value={scheduledFor} min={today} onChange={(e) => setScheduledFor(e.target.value)} />
                                </Field>
                            )}
                        </div>
                    )}
                </Card>
            )}

            {/* 3. Ejercicios */}
            <section className="space-y-4" aria-label="Ejercicios">
                <Card className="space-y-4">
                    <StepTitle n={++step}>Ejercicios</StepTitle>

                    {!isSingle && <DayTabs value={day} onChange={setDay} counts={counts} label="Día de la rutina" />}

                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h3 className="font-semibold">
                            {isSingle ? 'Sesión' : dayName}
                            <span className="ml-2 text-sm font-normal text-slate-500">
                                {items.length === 0 ? (isSingle ? 'Sin ejercicios' : 'Descanso') : `${items.length} ${items.length === 1 ? 'ejercicio' : 'ejercicios'}`}
                            </span>
                        </h3>
                        {!isSingle && DAYS.some(({ day: d }) => d !== day && lists[d].length > 0) && (
                            <label className="flex items-center gap-2 text-sm text-slate-600">
                                <Copy className="size-4 shrink-0" aria-hidden="true" />
                                <span className="sr-only">Copiar de otro día</span>
                                <Select value="" onChange={(e) => copyFrom(e.target.value)} className="w-auto py-2" aria-label="Copiar de otro día">
                                    <option value="">Copiar de otro día…</option>
                                    {DAYS.filter(({ day: d }) => d !== day && lists[d].length > 0).map(({ day: d, name }) => (
                                        <option key={d} value={d}>
                                            {name} ({lists[d].length})
                                        </option>
                                    ))}
                                </Select>
                            </label>
                        )}
                    </div>

                    {items.length === 0 ? (
                        <div className="rounded-2xl border-2 border-dashed border-slate-200 px-4 py-8 text-center">
                            <Dumbbell className="mx-auto size-8 text-slate-300" aria-hidden="true" />
                            <p className="mt-2 text-sm text-slate-500">
                                {isSingle ? 'Agrega los ejercicios de la sesión.' : `${dayName} queda como día de descanso si no agregas ejercicios.`}
                            </p>
                        </div>
                    ) : (
                        <ol className="space-y-3">
                            {items.map((item, index) => (
                                <ItemRow
                                    key={item.key}
                                    item={item}
                                    index={index}
                                    total={items.length}
                                    exercise={byId[item.exercise_id]}
                                    errors={errors.items[item.key]}
                                    onChange={(field, value) => changeItem(item.key, field, value)}
                                    onMove={(delta) => moveItem(index, delta)}
                                    onRemove={() => removeItem(item.key)}
                                />
                            ))}
                        </ol>
                    )}

                    <Button variant="secondary" size="lg" onClick={() => setPicker(true)} className="text-base">
                        <ListPlus className="size-5" aria-hidden="true" /> Agregar ejercicios
                    </Button>
                </Card>
            </section>

            {errors.meta.items && <p className="text-sm text-red-600">{errors.meta.items}</p>}

            {/* Barra de guardado fija (en el portal queda arriba de la barra de pestañas) */}
            <div
                className={cx(
                    'sticky z-10 -mx-4 border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur sm:mx-0 sm:rounded-2xl sm:border',
                    isPanel ? 'bottom-0' : 'bottom-[calc(4.5rem+env(safe-area-inset-bottom))]',
                )}
            >
                <div className="flex items-center justify-between gap-3">
                    <p className="min-w-0 text-sm text-slate-500">
                        {total} {total === 1 ? 'ejercicio' : 'ejercicios'}
                        {dirty && <span className="block text-xs">Cambios sin guardar</span>}
                    </p>
                    <Button onClick={save} disabled={saving} className="shrink-0 px-5 py-3">
                        <Save className="size-4" aria-hidden="true" /> {saving ? 'Guardando…' : label}
                    </Button>
                </div>
            </div>

            <ExercisePicker
                open={picker}
                onClose={() => setPicker(false)}
                catalog={catalog}
                muscleGroups={muscleGroups}
                onAdd={addExercises}
                targetLabel={isSingle ? 'la sesión' : dayName}
            />
        </div>
    );
}

function StepTitle({ n, children }) {
    return (
        <h2 className="flex items-center gap-2 text-lg font-semibold">
            <span className="grid size-7 shrink-0 place-items-center rounded-full bg-primary text-sm font-bold text-on-primary">{n}</span>
            {children}
        </h2>
    );
}

function TypeButton({ active, disabled, icon: IconCmp, title, hint, onClick }) {
    return (
        <button
            type="button"
            aria-pressed={active}
            disabled={disabled}
            onClick={onClick}
            className={cx(
                'flex min-h-20 flex-col items-center justify-center gap-1 rounded-2xl px-2 py-3 text-center transition disabled:cursor-not-allowed',
                active ? 'bg-primary text-on-primary shadow-sm' : 'bg-white text-slate-800 ring-1 ring-slate-300 hover:bg-slate-50',
                disabled && !active && 'opacity-40',
            )}
        >
            <IconCmp className="size-6" aria-hidden="true" />
            <span className="text-sm leading-tight font-bold">{title}</span>
            <span className={cx('text-xs leading-tight', active ? 'opacity-80' : 'text-slate-500')}>{hint}</span>
        </button>
    );
}

function ItemRow({ item, index, total, exercise, errors = {}, onChange, onMove, onRemove }) {
    const id = (f) => `${item.key}-${f}`;
    return (
        <li>
            <div className={cx('space-y-3 rounded-2xl bg-slate-50 p-3 ring-1', Object.keys(errors).length ? 'ring-red-300' : 'ring-slate-200')}>
                <div className="flex items-start gap-2">
                    <span className="mt-1 grid size-7 shrink-0 place-items-center rounded-full bg-white text-xs font-bold ring-1 ring-slate-200">{index + 1}</span>
                    <div className="min-w-0 flex-1">
                        <p className="leading-tight font-semibold">{exercise?.name ?? 'Ejercicio eliminado'}</p>
                        <p className="text-xs text-slate-500">
                            {exercise?.muscle_group_label}
                            {exercise?.equipment_label && ` · ${exercise.equipment_label}`}
                        </p>
                    </div>
                    <div className="flex shrink-0">
                        <IconButton label="Subir" onClick={() => onMove(-1)} disabled={index === 0}>
                            <ArrowUp className="size-4" />
                        </IconButton>
                        <IconButton label="Bajar" onClick={() => onMove(1)} disabled={index === total - 1}>
                            <ArrowDown className="size-4" />
                        </IconButton>
                        <IconButton label="Quitar" onClick={onRemove} className="text-red-600">
                            <Trash2 className="size-4" />
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
                <Field label="Nota (opcional)" htmlFor={id('notes')} error={errors.notes}>
                    <Input id={id('notes')} value={item.notes} onChange={(e) => onChange('notes', e.target.value)} maxLength={200} placeholder="Ej. Baja lento, 3 segundos" />
                </Field>
                {(errors.exercise_id || errors.day_of_week) && <p className="text-sm text-red-600">{errors.exercise_id || errors.day_of_week}</p>}
            </div>
        </li>
    );
}

function IconButton({ label, className, children, ...props }) {
    return (
        <button
            type="button"
            aria-label={label}
            title={label}
            className={cx('grid size-10 place-items-center rounded-lg text-slate-600 hover:bg-white disabled:opacity-30', className)}
            {...props}
        >
            {children}
        </button>
    );
}
