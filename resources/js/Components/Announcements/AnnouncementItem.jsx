import { Megaphone, Pin, Sparkles } from 'lucide-react';
import { cx } from '@/Components/ui';
import { formatDate } from '@/lib/format';

/**
 * Un aviso tal como lo ve el socio. Las promociones se destacan con el
 * color primario de la marca; los operativos son sobrios.
 *
 *   <AnnouncementItem announcement={{ type, title, body, pinned, expires_at }} />
 */
export default function AnnouncementItem({ announcement, className }) {
    const promo = announcement.type === 'promocion';
    const TypeIcon = promo ? Sparkles : Megaphone;

    return (
        <article
            className={cx(
                'rounded-2xl p-4',
                promo ? 'bg-primary text-on-primary shadow-sm' : 'bg-white text-slate-900 ring-1 ring-slate-200',
                className,
            )}
        >
            <div className="flex items-start gap-3">
                <span
                    className={cx(
                        'grid size-9 shrink-0 place-items-center rounded-xl',
                        promo ? 'bg-white/20' : 'bg-slate-100 text-slate-500',
                    )}
                >
                    <TypeIcon className="size-5" aria-hidden="true" />
                </span>
                <div className="min-w-0 flex-1">
                    <div className="flex items-start justify-between gap-2">
                        <h3 className="font-semibold leading-snug break-words">{announcement.title || 'Título del aviso'}</h3>
                        {announcement.pinned && <Pin className={cx('mt-0.5 size-4 shrink-0', !promo && 'text-slate-400')} aria-label="Fijado" />}
                    </div>
                    <p className={cx('mt-1 text-sm whitespace-pre-line break-words', promo ? 'opacity-90' : 'text-slate-600')}>
                        {announcement.body || 'Contenido del aviso.'}
                    </p>
                    {announcement.expires_at && (
                        <p className={cx('mt-2 text-xs', promo ? 'opacity-80' : 'text-slate-400')}>
                            {promo ? 'Válido hasta' : 'Hasta'} el {formatDate(announcement.expires_at)}
                        </p>
                    )}
                </div>
            </div>
        </article>
    );
}
