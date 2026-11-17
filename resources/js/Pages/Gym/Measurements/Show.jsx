import { ArrowLeft } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Badge, Button, Card, EmptyState, PageHeader, Stat } from '@/Components/ui';
import { formatDate } from '@/lib/format';
import AchievementCards from '@/Components/Progress/AchievementCards';
import TrendCharts from '@/Components/Progress/TrendCharts';
import MeasurementForm from '@/Components/Progress/MeasurementForm';
import MeasurementHistory from '@/Components/Progress/MeasurementHistory';

/** Ficha del socio: historial, gráficas y registrar valoración. */
export default function Show({ member, progress, streak, today }) {
    const { latest, reminder } = progress;

    return (
        <AppLayout title={`Valoración · ${member.name}`}>
            <Button href="/panel/valoraciones" variant="ghost" size="sm" className="mb-3">
                <ArrowLeft className="size-4" aria-hidden="true" /> Valoraciones
            </Button>
            <PageHeader
                title={member.name}
                description={member.email}
                actions={reminder ? <Badge tone="amber">{reminder.reason === 'never' ? 'Sin valoraciones' : `Pendiente · ${reminder.daysSince} días`}</Badge> : <Badge tone="green">Al día</Badge>}
            />

            <div className="mb-6 grid gap-3 sm:grid-cols-3">
                <Stat label="Última valoración" value={latest ? formatDate(latest.measured_on) : '—'} icon="Ruler" />
                <Stat label="Racha" value={`🔥 ${streak.streakWeeks} ${streak.streakWeeks === 1 ? 'semana' : 'semanas'}`} icon="TrendingUp" />
                <Stat label="Esta semana" value={`${streak.attendedThisWeek} de ${streak.goal} días`} icon="Activity" />
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="min-w-0 space-y-6 lg:col-span-2">
                    {progress.history.length ? (
                        <>
                            <AchievementCards achievements={progress.achievements} baseline={progress.baseline} title="Avance contra su inicio" />
                            <TrendCharts data={progress.chart} metrics={progress.metrics} />
                            <MeasurementHistory history={progress.history} metrics={progress.metrics} selfLabel="Socio" staffLabel="Personal" />
                        </>
                    ) : (
                        <EmptyState icon="Ruler" title="Sin valoraciones">
                            Registra la primera: será su línea base.
                        </EmptyState>
                    )}
                </div>

                <Card className="h-fit lg:sticky lg:top-6">
                    <h2 className="mb-4 text-lg font-bold">Nueva valoración</h2>
                    <MeasurementForm action={`/panel/valoraciones/${member.id}`} metrics={progress.metrics} today={today} submitLabel="Guardar valoración" />
                </Card>
            </div>
        </AppLayout>
    );
}
