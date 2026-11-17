import { usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import AnnouncementsCard from '@/Components/Member/AnnouncementsCard';
import MeasurementReminderCard from '@/Components/Member/MeasurementReminderCard';
import MembershipCard from '@/Components/Member/MembershipCard';
import StreakCard from '@/Components/Member/StreakCard';
import TodayRoutineCard from '@/Components/Member/TodayRoutineCard';

/**
 * Inicio del socio, en orden de lo que más se usa dentro del gimnasio:
 * credencial → avisos → racha → rutina de hoy → recordatorio de medidas.
 */
export default function Home({ membership, todayRoutine, progress, announcementsCard }) {
    const { auth } = usePage().props;

    return (
        <AppLayout title="Inicio">
            <h1 className="text-2xl font-bold">Hola, {auth.user.name.split(' ')[0]}</h1>
            <MembershipCard {...membership} />
            <AnnouncementsCard {...announcementsCard} />
            <StreakCard streak={progress.streak} href="/app/progreso" />
            <TodayRoutineCard {...todayRoutine} />
            <MeasurementReminderCard reminder={progress.measurementReminder} />
        </AppLayout>
    );
}
