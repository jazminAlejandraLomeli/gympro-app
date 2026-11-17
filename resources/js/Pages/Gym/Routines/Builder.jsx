import { ArrowLeft, Inbox, LayoutTemplate, UserRound } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, Button, Card, PageHeader } from '@/Components/ui';
import RoutineBuilder from '@/Components/Training/RoutineBuilder';
import { timeAgo, TYPE_LABELS } from '@/Components/Training/days';

/** Alta y edición de rutinas en el panel (coach y dueño). */
export default function RoutineBuilderPage({ mode, routine, initial, request, catalog, muscleGroups, members, today }) {
    const isCreate = mode === 'create';
    const backHref = request ? '/panel/rutinas?tab=solicitudes' : routine && !routine.is_template ? '/panel/rutinas?tab=historial' : '/panel/rutinas?tab=plantillas';

    return (
        <AppLayout title={isCreate ? 'Nueva rutina' : `Editar ${initial.name}`}>
            <div className="mx-auto max-w-3xl">
                <Button href={backHref} variant="ghost" size="sm" className="mb-2 -ml-2">
                    <ArrowLeft className="size-4" aria-hidden="true" /> Rutinas
                </Button>
                <PageHeader
                    title={isCreate ? 'Nueva rutina' : 'Editar rutina'}
                    description={
                        isCreate
                            ? 'Arma la rutina en tres pasos: datos, para quién y ejercicios.'
                            : routine.is_template
                              ? 'Plantilla del gimnasio. Los socios que ya la tienen conservan su copia.'
                              : undefined
                    }
                />

                {request && (
                    <Card className="mb-5 flex gap-3 ring-amber-200">
                        <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-amber-100 text-amber-800">
                            <Inbox className="size-5" aria-hidden="true" />
                        </span>
                        <div className="min-w-0 text-sm">
                            <p className="font-semibold">
                                Solicitud de {request.user.name} <span className="font-normal text-slate-500">· {timeAgo(request.created_at)}</span>
                            </p>
                            <p className="mt-0.5 text-slate-700">“{request.goal}”</p>
                            <p className="mt-1 flex flex-wrap gap-2">
                                <Badge tone="amber">{TYPE_LABELS[request.kind]}</Badge>
                                {request.days_per_week && <Badge>{request.days_per_week} días por semana</Badge>}
                            </p>
                            <p className="mt-2 text-xs text-slate-500">Al guardar se le asigna y la solicitud queda atendida.</p>
                        </div>
                    </Card>
                )}

                {!isCreate && (
                    <p className="mb-4 flex items-center gap-2 text-sm text-slate-600">
                        {routine.is_template ? (
                            <>
                                <LayoutTemplate className="size-4" aria-hidden="true" /> Plantilla del gimnasio
                            </>
                        ) : (
                            <>
                                <UserRound className="size-4" aria-hidden="true" /> Rutina de <strong>{routine.member?.name}</strong> · {routine.author}
                            </>
                        )}
                    </p>
                )}

                <RoutineBuilder
                    audience="panel"
                    initial={initial}
                    submitUrl={isCreate ? '/panel/rutinas' : `/panel/rutinas/${routine.id}`}
                    method={isCreate ? 'post' : 'put'}
                    catalog={catalog}
                    muscleGroups={muscleGroups}
                    members={members}
                    lockType={!isCreate}
                    lockMember={Boolean(request)}
                    today={today}
                />
            </div>
        </AppLayout>
    );
}
