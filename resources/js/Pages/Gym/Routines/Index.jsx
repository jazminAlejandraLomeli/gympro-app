import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ChevronRight, Dumbbell, Plus, Users } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, Card, EmptyState, Field, Input, PageHeader } from '@/Components/ui';

/** Listado de rutinas del gimnasio. */
export default function RoutinesIndex({ routines }) {
    const [creating, setCreating] = useState(routines.length === 0);
    const form = useForm({ name: '', goal: '' });

    const submit = (e) => {
        e.preventDefault();
        form.post('/panel/rutinas');
    };

    return (
        <AppLayout title="Rutinas">
            <PageHeader
                title="Rutinas"
                description="Arma rutinas por día de la semana y asígnalas a tus socios."
                actions={
                    !creating && (
                        <Button onClick={() => setCreating(true)}>
                            <Plus className="size-4" aria-hidden="true" /> Nueva rutina
                        </Button>
                    )
                }
            />

            {creating && (
                <Card className="mb-5">
                    <form onSubmit={submit} className="space-y-4">
                        <h2 className="font-semibold">Nueva rutina</h2>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Nombre" htmlFor="name" error={form.errors.name}>
                                <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} placeholder="Ej. Hipertrofia 4 días" maxLength={100} />
                            </Field>
                            <Field label="Objetivo (opcional)" htmlFor="goal" error={form.errors.goal}>
                                <Input id="goal" value={form.data.goal} onChange={(e) => form.setData('goal', e.target.value)} placeholder="Ej. Ganar masa muscular" maxLength={120} />
                            </Field>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Creando…' : 'Crear y agregar ejercicios'}
                            </Button>
                            {routines.length > 0 && (
                                <Button variant="ghost" onClick={() => setCreating(false)}>
                                    Cancelar
                                </Button>
                            )}
                        </div>
                    </form>
                </Card>
            )}

            {routines.length === 0 ? (
                <EmptyState icon="ClipboardList" title="Aún no hay rutinas">
                    Crea tu primera rutina; después agregas ejercicios del catálogo a cada día.
                </EmptyState>
            ) : (
                <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    {routines.map((r) => (
                        <li key={r.id}>
                            <Link href={`/panel/rutinas/${r.id}`} className="block h-full">
                                <Card className="flex h-full items-center gap-3 transition hover:ring-slate-300">
                                    <div className="min-w-0 flex-1">
                                        <h3 className="truncate font-semibold">{r.name}</h3>
                                        {r.goal && <p className="truncate text-sm text-slate-500">{r.goal}</p>}
                                        <p className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                                            <span className="inline-flex items-center gap-1">
                                                <Dumbbell className="size-3.5" aria-hidden="true" /> {r.items_count} ejercicios
                                            </span>
                                            <span className="inline-flex items-center gap-1">
                                                <Users className="size-3.5" aria-hidden="true" /> {r.members_count} {r.members_count === 1 ? 'socio' : 'socios'}
                                            </span>
                                        </p>
                                    </div>
                                    <ChevronRight className="size-5 shrink-0 text-slate-400" aria-hidden="true" />
                                </Card>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </AppLayout>
    );
}
