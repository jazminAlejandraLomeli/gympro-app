import { router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Camera, Pencil, Plus, Save, Search, Trash2, Upload, VideoOff } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, Button, Card, cx, EmptyState, Field, Input, PageHeader, Pagination, Select, Textarea } from '@/Components/ui';
import Chip from '@/Components/Training/Chip';
import ClipStatusBadge from '@/Components/Training/ClipStatusBadge';
import ExerciseClip from '@/Components/Training/ExerciseClip';
import Sheet from '@/Components/Training/Sheet';
import { formatBytes } from '@/Components/Training/days';

const MAX_CLIP_BYTES = 50 * 1024 * 1024;
const POLL_MS = 4000;
const DIFFICULTY_TONES = { principiante: 'green', intermedio: 'amber', avanzado: 'red' };

/**
 * Biblioteca de ejercicios. El alta y la edición abren un panel lateral
 * (pantalla completa en el celular): primero los datos y, ya guardado,
 * el video de muestra en el mismo panel (?editar={id}).
 */
export default function ExercisesIndex({ exercises, filters, editing, muscleGroups, equipment, difficulties }) {
    const [creating, setCreating] = useState(false);
    const [q, setQ] = useState(filters.q);

    // Polling ligero mientras algún video se está procesando
    const processing = exercises.data.some((e) => e.clip_status === 'processing') || editing?.clip_status === 'processing';
    useEffect(() => {
        if (!processing) return;
        const t = setInterval(() => router.reload({ only: ['exercises', 'editing'] }), POLL_MS);
        return () => clearInterval(t);
    }, [processing]);

    const query = (next = {}) => {
        const params = { ...filters, q, ...next };
        return Object.fromEntries(Object.entries(params).filter(([, v]) => v !== '' && v !== null && v !== undefined));
    };
    const visit = (params) => router.get('/panel/ejercicios', params, { preserveState: true, preserveScroll: true, replace: true });

    const applyFilters = (next) => visit(query(next));
    const openEdit = (exercise) => {
        setCreating(false);
        visit(query({ editar: exercise.id }));
    };
    const close = () => {
        setCreating(false);
        if (editing) visit(query());
    };

    const hasFilters = filters.grupo || filters.equipo || filters.q;

    return (
        <AppLayout title="Ejercicios">
            <PageHeader
                title="Biblioteca de ejercicios"
                description="Ejercicios del gimnasio con su objetivo, indicaciones y un video corto de muestra."
                actions={
                    <Button onClick={() => setCreating(true)}>
                        <Plus className="size-4" aria-hidden="true" /> Nuevo ejercicio
                    </Button>
                }
            />

            <div className="mb-5 space-y-3">
                <div className="grid gap-2 sm:grid-cols-[minmax(0,1fr)_14rem]">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            applyFilters({});
                        }}
                        className="relative"
                    >
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                        <Input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Buscar por nombre" aria-label="Buscar ejercicio" className="pl-9" />
                    </form>
                    <Select value={filters.equipo} onChange={(e) => applyFilters({ equipo: e.target.value })} aria-label="Filtrar por equipo">
                        <option value="">Todo el equipo</option>
                        {equipment.map((o) => (
                            <option key={o.value} value={o.value}>
                                {o.label}
                            </option>
                        ))}
                    </Select>
                </div>
                <div className="flex flex-wrap gap-1.5" role="group" aria-label="Filtrar por grupo muscular">
                    <Chip active={!filters.grupo} onClick={() => applyFilters({ grupo: '' })}>
                        Todos
                    </Chip>
                    {muscleGroups.map((g) => (
                        <Chip key={g.value} active={filters.grupo === g.value} onClick={() => applyFilters({ grupo: g.value })}>
                            {g.label}
                        </Chip>
                    ))}
                </div>
            </div>

            {exercises.data.length === 0 ? (
                <EmptyState
                    icon="Clapperboard"
                    title={hasFilters ? 'Sin resultados' : 'Aún no hay ejercicios'}
                    action={<Button onClick={() => setCreating(true)}>Agregar ejercicio</Button>}
                >
                    {hasFilters ? 'Prueba con otro filtro.' : 'Agrega tus ejercicios con su objetivo y graba un video corto de cada uno.'}
                </EmptyState>
            ) : (
                <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {exercises.data.map((exercise) => (
                        <li key={exercise.id}>
                            <ExerciseCard exercise={exercise} onEdit={() => openEdit(exercise)} />
                        </li>
                    ))}
                </ul>
            )}

            <Pagination links={exercises.links} />

            <ExerciseSheet
                open={creating || Boolean(editing)}
                exercise={creating ? null : editing}
                muscleGroups={muscleGroups}
                equipment={equipment}
                difficulties={difficulties}
                onClose={close}
                onCreated={() => setCreating(false)}
            />
        </AppLayout>
    );
}

function ExerciseCard({ exercise, onEdit }) {
    return (
        <Card className="flex h-full flex-col overflow-hidden p-0 sm:p-0">
            <button type="button" onClick={onEdit} className="relative block text-left" aria-label={`Editar ${exercise.name}`}>
                <ExerciseClip src={exercise.clip_url} title={exercise.name} className="aspect-video w-full" />
                {exercise.clip_status === 'processing' && (
                    <span className="absolute inset-0 grid place-items-center bg-black/50 text-sm font-semibold text-white" role="status">
                        Procesando…
                    </span>
                )}
            </button>
            <div className="flex flex-1 flex-col gap-2 p-4">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                        <h3 className="leading-tight font-semibold">{exercise.name}</h3>
                        <p className="text-sm text-slate-500">
                            {exercise.muscle_group_label}
                            {exercise.equipment_label && ` · ${exercise.equipment_label}`}
                        </p>
                    </div>
                    <ClipStatusBadge status={exercise.clip_status} />
                </div>
                {exercise.objective && <p className="line-clamp-2 text-sm text-slate-700">{exercise.objective}</p>}
                {exercise.difficulty_label && (
                    <div>
                        <Badge tone={DIFFICULTY_TONES[exercise.difficulty] ?? 'slate'}>{exercise.difficulty_label}</Badge>
                    </div>
                )}
                <div className="mt-auto pt-2">
                    <Button variant="secondary" onClick={onEdit} className="w-full">
                        <Pencil className="size-4" aria-hidden="true" /> Editar y video
                    </Button>
                </div>
            </div>
        </Card>
    );
}

function ExerciseSheet({ open, exercise, muscleGroups, equipment, difficulties, onClose, onCreated }) {
    const destroy = () => {
        if (confirm(`¿Eliminar «${exercise.name}»? También se quitará de las rutinas donde aparece.`)) {
            router.delete(`/panel/ejercicios/${exercise.id}`);
        }
    };

    return (
        <Sheet
            open={open}
            onClose={onClose}
            title={exercise ? exercise.name : 'Nuevo ejercicio'}
            description={exercise ? 'Edita los datos o cambia el video de muestra.' : 'Paso 1: los datos. Paso 2: el video de muestra.'}
            footer={
                <div className="flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-between">
                    {exercise ? (
                        <Button variant="ghost" onClick={destroy} className="text-red-600">
                            <Trash2 className="size-4" aria-hidden="true" /> Eliminar
                        </Button>
                    ) : (
                        <span />
                    )}
                    <Button type="submit" form="exercise-form" className="py-3 sm:px-6">
                        <Save className="size-4" aria-hidden="true" /> {exercise ? 'Guardar cambios' : 'Guardar y seguir al video'}
                    </Button>
                </div>
            }
        >
            <div className="space-y-6">
                <ExerciseForm
                    key={exercise?.id ?? 'new'}
                    exercise={exercise}
                    muscleGroups={muscleGroups}
                    equipment={equipment}
                    difficulties={difficulties}
                    onCreated={onCreated}
                />
                <VideoSection exercise={exercise} />
            </div>
        </Sheet>
    );
}

function ExerciseForm({ exercise, muscleGroups, equipment, difficulties, onCreated }) {
    const form = useForm({
        name: exercise?.name ?? '',
        muscle_group: exercise?.muscle_group ?? muscleGroups[0]?.value ?? '',
        equipment: exercise?.equipment ?? '',
        difficulty: exercise?.difficulty ?? '',
        objective: exercise?.objective ?? '',
        instructions: exercise?.instructions ?? '',
        notes: exercise?.notes ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.transform((data) => ({ ...data, equipment: data.equipment || null, difficulty: data.difficulty || null }));
        if (exercise) form.put(`/panel/ejercicios/${exercise.id}`, { preserveScroll: true, preserveState: true });
        else form.post('/panel/ejercicios', { preserveScroll: true, onSuccess: onCreated });
    };

    return (
        <form id="exercise-form" onSubmit={submit} className="space-y-4">
            <h3 className="text-sm font-semibold tracking-wide text-slate-500 uppercase">Datos del ejercicio</h3>
            <Field label="Nombre" htmlFor="ex-name" error={form.errors.name}>
                <Input id="ex-name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} maxLength={100} placeholder="Ej. Press de banca con barra" />
            </Field>
            <Field label="Objetivo" htmlFor="ex-objective" error={form.errors.objective} hint="Para qué sirve, en pocas palabras.">
                <Input
                    id="ex-objective"
                    value={form.data.objective}
                    onChange={(e) => form.setData('objective', e.target.value)}
                    maxLength={150}
                    placeholder="Ej. Fuerza de pecho y tríceps"
                />
            </Field>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Grupo muscular" htmlFor="ex-group" error={form.errors.muscle_group}>
                    <Select id="ex-group" value={form.data.muscle_group} onChange={(e) => form.setData('muscle_group', e.target.value)}>
                        {muscleGroups.map((g) => (
                            <option key={g.value} value={g.value}>
                                {g.label}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label="Equipo" htmlFor="ex-equipment" error={form.errors.equipment}>
                    <Select id="ex-equipment" value={form.data.equipment} onChange={(e) => form.setData('equipment', e.target.value)}>
                        <option value="">Sin especificar</option>
                        {equipment.map((o) => (
                            <option key={o.value} value={o.value}>
                                {o.label}
                            </option>
                        ))}
                    </Select>
                </Field>
            </div>
            <fieldset>
                <legend className="mb-1.5 block text-sm font-medium text-slate-700">Dificultad</legend>
                <div className="grid grid-cols-2 gap-1.5 sm:grid-cols-4">
                    <Chip active={!form.data.difficulty} onClick={() => form.setData('difficulty', '')}>
                        Sin nivel
                    </Chip>
                    {difficulties.map((d) => (
                        <Chip key={d.value} active={form.data.difficulty === d.value} onClick={() => form.setData('difficulty', d.value)}>
                            {d.label}
                        </Chip>
                    ))}
                </div>
                {form.errors.difficulty && <p className="mt-1 text-sm text-red-600">{form.errors.difficulty}</p>}
            </fieldset>
            <Field label="Cómo se hace" htmlFor="ex-instructions" error={form.errors.instructions} hint="Postura, recorrido y respiración.">
                <Textarea id="ex-instructions" value={form.data.instructions} onChange={(e) => form.setData('instructions', e.target.value)} maxLength={2000} />
            </Field>
            <Field label="Notas: tips y precauciones" htmlFor="ex-notes" error={form.errors.notes}>
                <Textarea id="ex-notes" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} maxLength={2000} className="min-h-20" />
            </Field>
            {form.processing && <p className="text-sm text-slate-500">Guardando…</p>}
        </form>
    );
}

function VideoSection({ exercise }) {
    if (!exercise) {
        return (
            <section className="space-y-2">
                <h3 className="text-sm font-semibold tracking-wide text-slate-500 uppercase">Video de muestra</h3>
                <p className="rounded-2xl border-2 border-dashed border-slate-200 px-4 py-6 text-center text-sm text-slate-500">
                    Guarda el ejercicio y aquí mismo podrás grabar o subir su video (3 a 5 segundos).
                </p>
            </section>
        );
    }

    const removeClip = () => {
        if (confirm('¿Quitar el video de este ejercicio?')) {
            router.delete(`/panel/ejercicios/${exercise.id}/clip`, { preserveScroll: true, preserveState: true });
        }
    };

    return (
        <section className="space-y-3">
            <div className="flex items-center justify-between gap-2">
                <h3 className="text-sm font-semibold tracking-wide text-slate-500 uppercase">Video de muestra</h3>
                <ClipStatusBadge status={exercise.clip_status} />
            </div>
            <div className="relative overflow-hidden rounded-2xl">
                <ExerciseClip src={exercise.clip_url} title={exercise.name} className="aspect-video w-full" />
                {exercise.clip_status === 'processing' && (
                    <span className="absolute inset-0 grid place-items-center bg-black/50 px-4 text-center text-sm font-semibold text-white" role="status">
                        Procesando… tarda unos segundos
                    </span>
                )}
            </div>
            {exercise.clip_status === 'failed' && (
                <p className="text-sm text-red-600">No pudimos procesar el último video. Intenta grabarlo de nuevo (3 a 5 segundos).</p>
            )}
            {exercise.clip_size_bytes && exercise.clip_status === 'ready' && <p className="text-xs text-slate-400">Peso del video: {formatBytes(exercise.clip_size_bytes)}</p>}
            <ClipUploader exercise={exercise} />
            {exercise.clip_url && (
                <Button variant="ghost" size="sm" onClick={removeClip}>
                    <VideoOff className="size-4" aria-hidden="true" /> Quitar video
                </Button>
            )}
        </section>
    );
}

/**
 * Dos entradas: "Grabar" abre la cámara trasera del celular
 * (capture="environment"); "Subir video o GIF" deja elegir de la galería
 * (los GIF animados se convierten a MP4 en el servidor).
 */
function ClipUploader({ exercise }) {
    const form = useForm({ clip: null });
    const [localError, setLocalError] = useState(null);
    const cameraRef = useRef(null);
    const fileRef = useRef(null);
    const busy = form.processing || exercise.clip_status === 'processing';

    const upload = (file) => {
        setLocalError(null);
        if (!file) return;
        if (file.size > MAX_CLIP_BYTES) {
            setLocalError('El archivo pesa más de 50 MB. Graba o elige solo 3 a 5 segundos.');
            return;
        }
        form.transform(() => ({ clip: file }));
        form.post(`/panel/ejercicios/${exercise.id}/clip`, {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                if (cameraRef.current) cameraRef.current.value = '';
                if (fileRef.current) fileRef.current.value = '';
            },
        });
    };

    return (
        <div className="space-y-2">
            <div className="grid grid-cols-2 gap-2">
                <label className={cx('cursor-pointer', busy && 'pointer-events-none opacity-50')}>
                    <input ref={cameraRef} type="file" accept="video/*" capture="environment" className="sr-only" disabled={busy} onChange={(e) => upload(e.target.files[0])} />
                    <span className="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-primary px-3 py-2.5 text-sm font-semibold text-on-primary">
                        <Camera className="size-4" aria-hidden="true" /> {exercise.clip_url ? 'Regrabar' : 'Grabar'}
                    </span>
                </label>
                <label className={cx('cursor-pointer', busy && 'pointer-events-none opacity-50')}>
                    <input
                        ref={fileRef}
                        type="file"
                        accept="video/mp4,video/quicktime,video/webm,video/*,image/gif"
                        className="sr-only"
                        disabled={busy}
                        onChange={(e) => upload(e.target.files[0])}
                    />
                    <span className="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-white px-3 py-2.5 text-sm font-semibold text-slate-900 ring-1 ring-slate-300">
                        <Upload className="size-4" aria-hidden="true" /> Subir video o GIF
                    </span>
                </label>
            </div>
            {form.progress && (
                <div className="h-2 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuenow={form.progress.percentage} aria-valuemin={0} aria-valuemax={100}>
                    <div className="h-full bg-primary transition-all" style={{ width: `${form.progress.percentage ?? 0}%` }} />
                </div>
            )}
            {(localError || form.errors.clip) && <p className="text-sm text-red-600">{localError || form.errors.clip}</p>}
            <p className="text-xs text-slate-500">Video (MP4, MOV, WebM) o GIF animado de hasta 50 MB. Se convierte a video ligero de máximo 6 segundos, sin audio.</p>
        </div>
    );
}
