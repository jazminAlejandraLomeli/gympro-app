import { Megaphone } from 'lucide-react';
import AnnouncementItem from '@/Components/Announcements/AnnouncementItem';

/**
 * Tarjeta del inicio del socio con los avisos vigentes.
 * Props: las que devuelve App\Services\Announcements\VisibleAnnouncements::forMemberHome().
 *
 *   <AnnouncementsCard {...announcementsCard} />
 *
 * Si no hay avisos vigentes no muestra nada.
 */
export default function AnnouncementsCard({ announcements = [] }) {
    if (!announcements.length) return null;

    return (
        <section aria-labelledby="avisos-titulo" className="space-y-3">
            <h2 id="avisos-titulo" className="flex items-center gap-2 text-lg font-bold">
                <Megaphone className="size-5" aria-hidden="true" />
                Avisos del gimnasio
            </h2>
            <div className="space-y-3">
                {announcements.map((a) => (
                    <AnnouncementItem key={a.id} announcement={a} />
                ))}
            </div>
        </section>
    );
}
