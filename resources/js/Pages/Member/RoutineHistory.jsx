import { Link, router } from '@inertiajs/react';
import { ArrowLeft, ChevronRight, RotateCcw } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, Button, EmptyState, Pagination } from '@/Components/ui';
import { formatDate } from '@/lib/format';
import { authorLabel } from '@/Components/Training/days';

/** Todas las rutinas que ha tenido el socio, de la más reciente a la más vieja. */
export default function RoutineHistory({ assignments }) {
    const reuse = (routine) => router.post(`/app/rutina/${routine.id}/usar`);

    return (
        <AppLayout title="Mi historial">
            <div>
                <Button href="/app/rutina" variant="ghost" size="sm" className="-ml-2">
                    <ArrowLeft className="size-4" aria-hidden="true" /> Mi rutina
                </Button>
                <h1 className="mt-1 text-2xl font-bold">Mi historial</h1>
                <p className="text-sm text-slate-500">Abre cualquiera para verla o vuelve a usarla.</p>
            </div>

            {assignments.data.length === 0 ? (
                <EmptyState icon="Dumbbell" title="Aún no tienes rutinas" action={<Button href="/app/rutina/nueva">Crear mi rutina</Button>}>
                    Aquí verás todas las rutinas que te asigne tu coach y las que armes tú.
                </EmptyState>
            ) : (
                <ul className="space-y-3">
                    {assignments.data.map((a) => (
                        <li key={a.id} className="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/60">
                            <Link href={`/app/rutina/${a.routine.id}`} className="flex items-center gap-3 px-4 pt-3 pb-2">
                                <div className="min-w-0 flex-1">
                                    <p className="text-xs text-slate-500">{formatDate(a.date)}</p>
                                    <p className="truncate font-semibold">{a.routine.name}</p>
                                    <p className="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
                                        <Badge tone={a.routine.type === 'single' ? 'amber' : 'slate'}>{a.routine.type_label}</Badge>
                                        {a.is_current && <Badge tone="green">Vigente</Badge>}
                                        <span>{authorLabel(a.routine.author)}</span>
                                    </p>
                                </div>
                                <ChevronRight className="size-5 shrink-0 text-slate-400" aria-hidden="true" />
                            </Link>
                            {!a.is_current && (
                                <div className="border-t border-slate-100 px-2 py-1.5">
                                    <Button variant="ghost" onClick={() => reuse(a.routine)} className="w-full">
                                        <RotateCcw className="size-4" aria-hidden="true" /> Volver a usar
                                    </Button>
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            <Pagination links={assignments.links} />
        </AppLayout>
    );
}
