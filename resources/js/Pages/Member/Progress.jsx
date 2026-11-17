import AppLayout from '@/Layouts/AppLayout';
import { Card, EmptyState, PageHeader } from '@/Components/ui';
import StreakCard from '@/Components/Member/StreakCard';
import MeasurementReminderCard from '@/Components/Member/MeasurementReminderCard';
import AchievementCards from '@/Components/Progress/AchievementCards';
import TrendCharts from '@/Components/Progress/TrendCharts';
import MeasurementForm from '@/Components/Progress/MeasurementForm';
import MeasurementHistory from '@/Components/Progress/MeasurementHistory';
import WeeklyGoalEditor from '@/Components/Progress/WeeklyGoalEditor';

/** Portal del socio: racha, meta, logros, tendencias e historial. */
export default function Progress({ streak, progress, defaultGoal }) {
    const hasHistory = progress.history.length > 0;

    return (
        <AppLayout title="Mi progreso">
            <PageHeader title="Mi progreso" description="Tu constancia y tu evolución física, mes con mes." />

            <div className="space-y-6">
                <StreakCard streak={streak} />
                <WeeklyGoalEditor key={streak.goal} goal={streak.goal} isCustom={streak.goalIsCustom} defaultGoal={defaultGoal} />

                <MeasurementReminderCard reminder={progress.reminder} href="#registrar" />

                {hasHistory ? (
                    <>
                        <AchievementCards achievements={progress.achievements} baseline={progress.baseline} />
                        <TrendCharts data={progress.chart} metrics={progress.metrics} />
                    </>
                ) : (
                    <EmptyState icon="TrendingUp" title="Aún no hay medidas">
                        Registra tu primera valoración y desde aquí verás cuánto has avanzado.
                    </EmptyState>
                )}

                <Card id="registrar" className="scroll-mt-20">
                    <h2 className="mb-1 text-lg font-bold">Registrar mis medidas</h2>
                    <p className="mb-4 text-sm text-slate-500">Mídete en ayunas y a la misma hora para comparar mejor. Llena solo lo que tengas.</p>
                    <MeasurementForm action="/app/progreso/medidas" metrics={progress.metrics} today={streak.today} />
                </Card>

                <MeasurementHistory history={progress.history} metrics={progress.metrics} />
            </div>
        </AppLayout>
    );
}
