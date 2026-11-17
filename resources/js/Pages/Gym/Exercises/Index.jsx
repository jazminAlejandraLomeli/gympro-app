import { router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Camera, Pencil, Plus, Search, Trash2, Upload, VideoOff } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, Card, cx, EmptyState, Field, Input, PageHeader, Pagination, Select, Textarea } from '@/Components/ui';
import ClipStatusBadge from '@/Components/Training/ClipStatusBadge';
import ExerciseClip from '@/Components/Training/ExerciseClip';
import { formatBytes } from '@/Components/Training/days';

const MAX_CLIP_BYTES = 50 * 1024 * 1024;
const POLL_MS = 4000;

/** Catálogo de ejercicios con clips grabados desde el celular. */
export default function ExercisesIndex({ exercises, filters, muscleGroups }) {
    const [editing, setEditing] = useState(null); // null | 'new' | ejercicio
    const [q, setQ] = useState(filters.q);

    // Polling ligero mientras algún clip se está procesando
    const processing = exercises.data.some((e) => e.clip_status === 'processing');
    useEffect(() => {
        if (!processing) return;
        const t = setInterval(() => router.reload({ only: ['exercises'] }), POLL_MS);
        return () => clearInterval(t);
    }, [processing]);

    const applyFilters = (next) =>
        router.get('/panel/ejercicios', { ...filters, q, ...next }, { preserveState: true, preserveScroll: true, replace: true });

    return (
        <AppLayout title="Ejercicios">
            <PageHeader
                title="Ejercicios"
                description="Tu catálogo con clips cortos (3 a 5 s) grabados en las máquinas."
                actions={
                    <Button onClick={() => setEditing('new')}>
                        <Plus className="size-4" aria-hidden="true" /> Nuevo ejercicio
                    </Button>
                }
            />

            {editing && <ExerciseForm exercise={editing === 'new' ? null : editing} muscleGroups={muscleGroups} onDone={() => setEditing(null)} />}

            <div className="mb-4 space-y-3">
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
                <div className="flex flex-wrap gap-1.5" role="group" aria-label="Filtrar por grupo muscular">
                    <FilterChip active={!filters.grupo} onClick={() => applyFilters({ grupo: '' })}>
                        Todos
                    </FilterChip>
                    {muscleGroups.map((g) => (
                        <FilterChip key={g.value} active={filters.grupo === g.value} onClick={() => applyFilters({ grupo: g.value })}>
                            {g.label}
                        </FilterChip>
                    ))}
                </div>
            </div>

            {exercises.data.length === 0 ? (
                <EmptyState
                    icon="Clapperboard"
                    title={filters.grupo || filters.q ? 'Sin resultados' : 'Aún no hay ejercicios'}
                    action={<Button onClick={() => setEditing('new')}>Agregar ejercicio</Button>}
                >
                    {filters.grupo || filters.q ? 'Prueba con otro filtro.' : 'Agrega tus ejercicios y graba un clip corto de cada uno en la máquina.'}
                </EmptyState>
            ) : (
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {exercises.data.map((exercise) => (
                        <ExerciseCard key={exercise.id} exercise={exercise} onEdit={() => setEditing(exercise)} />
                    ))}
                </div>
            )}

            <Pagination links={exercises.links} />
        </AppLayout>
    );
}

function FilterChip({ active, children, ...props }) {
    return (
        <button
            type="button"
            aria-pressed={active}
            className={cx(
                'rounded-full px-3 py-1.5 text-sm font-medium transition',
                active ? 'bg-primary text-on-primary' : 'bg-white text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50',
            )}
            {...props}
        >
            {children}
        </button>
    );
}

function ExerciseForm({ exercise, muscleGroups, onDone }) {
    const form = useForm({
        name: exercise?.name ?? '',
        muscle_group: exercise?.muscle_group ?? muscleGroups[0]?.value ?? '',
        instructions: exercise?.instructions ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: onDone };
        if (exercise) form.put(`/panel/ejercicios/${exercise.id}`, options);
        else form.post('/panel/ejercicios', options);
    };

    return (
        <Card className="mb-5">
            <form onSubmit={submit} className="space-y-4">
                <h2 className="font-semibold">{exercise ? 'Editar ejercicio' : 'Nuevo ejercicio'}</h2>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Nombre" htmlFor="name" error={form.errors.name}>
                        <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} maxLength={100} autoFocus />
                    </Field>
                    <Field label="Grupo muscular" htmlFor="muscle_group" error={form.errors.muscle_group}>
                        <Select id="muscle_group" value={form.data.muscle_group} onChange={(e) => form.setData('muscle_group', e.target.value)}>
                            {muscleGroups.map((g) => (
                                <option key={g.value} value={g.value}>
                                    {g.label}
                                </option>
                            ))}
                        </Select>
                    </Field>
                </div>
                <Field label="Indicaciones (opcional)" htmlFor="instructions" error={form.errors.instructions} hint="Postura, respiración, errores comunes.">
                    <Textarea id="instructions" value={form.data.instructions} onChange={(e) => form.setData('instructions', e.target.value)} maxLength={2000} />
                </Field>
                <div className="flex flex-wrap gap-2">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Guardando…' : 'Guardar'}
                    </Button>
                    <Button variant="ghost" onClick={onDone}>
                        Cancelar
                    </Button>
                </div>
            </form>
        </Card>
    );
}

function ExerciseCard({ exercise, onEdit }) {
    const destroy = () => {
        if (confirm(`¿Eliminar «${exercise.name}»? También se quitará de las rutinas donde aparece.`)) {
            router.delete(`/panel/ejercicios/${exercise.id}`, { preserveScroll: true });
        }
    };
    const removeClip = () => {
        if (confirm('¿Quitar el clip de este ejercicio?')) {
            router.delete(`/panel/ejercicios/${exercise.id}/clip`, { preserveScroll: true });
        }
    };

    return (
        <Card className="flex flex-col overflow-hidden p-0 sm:p-0">
            <div className="relative">
                <ExerciseClip src={exercise.clip_url} title={exercise.name} className="aspect-video w-full" />
                {exercise.clip_status === 'processing' && (
                    <div className="absolute inset-0 grid place-items-center bg-black/50 text-sm font-semibold text-white" role="status">
                        Procesando clip…
                    </div>
                )}
            </div>
            <div className="flex flex-1 flex-col gap-3 p-4">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                        <h3 className="leading-tight font-semibold">{exercise.name}</h3>
                        <p className="text-sm text-slate-500">{exercise.muscle_group_label}</p>
                    </div>
                    <ClipStatusBadge status={exercise.clip_status} />
                </div>
                {exercise.clip_status === 'failed' && (
                    <p className="text-sm text-red-600">No pudimos procesar el último video. Intenta grabarlo de nuevo (3 a 5 segundos).</p>
                )}
                {exercise.clip_size_bytes && exercise.clip_status === 'ready' && (
                    <p className="text-xs text-slate-400">Peso del clip: {formatBytes(exercise.clip_size_bytes)}</p>
                )}

                <ClipUploader exercise={exercise} />

                <div className="mt-auto flex flex-wrap gap-1 border-t border-slate-100 pt-3">
                    <Button variant="ghost" size="sm" onClick={onEdit}>
                        <Pencil className="size-4" aria-hidden="true" /> Editar
                    </Button>
                    {exercise.clip_url && (
                        <Button variant="ghost" size="sm" onClick={removeClip}>
                            <VideoOff className="size-4" aria-hidden="true" /> Quitar clip
                        </Button>
                    )}
                    <Button variant="ghost" size="sm" onClick={destroy} className="text-red-600">
                        <Trash2 className="size-4" aria-hidden="true" /> Eliminar
                    </Button>
                </div>
            </div>
        </Card>
    );
}

/**
 * Dos entradas: "Grabar" abre la cámara trasera del celular
 * (capture="environment"); "Subir video" deja elegir de la galería.
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
            setLocalError('El video pesa más de 50 MB. Graba solo 3 a 5 segundos.');
            return;
        }
        form.transform(() => ({ clip: file }));
        form.post(`/panel/ejercicios/${exercise.id}/clip`, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => {
                if (cameraRef.current) cameraRef.current.value = '';
                if (fileRef.current) fileRef.current.value = '';
            },
        });
    };

    const label = exercise.clip_url ? 'Regrabar' : 'Grabar clip';

    return (
        <div className="space-y-2">
            <div className="grid grid-cols-2 gap-2">
                <label className={cx('cursor-pointer', busy && 'pointer-events-none opacity-50')}>
                    <input
                        ref={cameraRef}
                        type="file"
                        accept="video/*"
                        capture="environment"
                        className="sr-only"
                        disabled={busy}
                        onChange={(e) => upload(e.target.files[0])}
                    />
                    <span className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-3 py-2.5 text-sm font-semibold text-on-primary">
                        <Camera className="size-4" aria-hidden="true" /> {label}
                    </span>
                </label>
                <label className={cx('cursor-pointer', busy && 'pointer-events-none opacity-50')}>
                    <input
                        ref={fileRef}
                        type="file"
                        accept="video/mp4,video/quicktime,video/webm,video/*"
                        className="sr-only"
                        disabled={busy}
                        onChange={(e) => upload(e.target.files[0])}
                    />
                    <span className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-white px-3 py-2.5 text-sm font-semibold text-slate-900 ring-1 ring-slate-300">
                        <Upload className="size-4" aria-hidden="true" /> Subir video
                    </span>
                </label>
            </div>
            {form.progress && (
                <div className="h-2 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-valuenow={form.progress.percentage} aria-valuemin={0} aria-valuemax={100}>
                    <div className="h-full bg-primary transition-all" style={{ width: `${form.progress.percentage ?? 0}%` }} />
                </div>
            )}
            {(localError || form.errors.clip) && <p className="text-sm text-red-600">{localError || form.errors.clip}</p>}
        </div>
    );
}
