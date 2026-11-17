import { QrCode } from 'lucide-react';
import { Button, Card } from '@/Components/ui';
import StatusBadge from '@/Components/Membership/StatusBadge';
import { creditsLabel } from '@/Components/Membership/validity';
import { formatDate } from '@/lib/format';

/**
 * Tarjeta de membresía para el inicio del socio.
 * Props: las de App\Services\Membership\MembershipSummary::forMemberHome($user)
 *
 *   <MembershipCard {...membership} />
 */
export default function MembershipCard({ hasMembership, status, statusLabel, planName, expiresOn, daysRemaining, credits, credentialUrl = '/app/credencial' }) {
    const byDate = daysRemaining !== null && daysRemaining !== undefined;

    let big;
    let caption;
    if (!hasMembership) {
        big = '—';
        caption = 'Pide en recepción que activen tu membresía.';
    } else if (byDate) {
        big = daysRemaining === 0 ? 'Hoy' : daysRemaining;
        caption = daysRemaining === 0 ? 'Tu membresía vence hoy' : `${daysRemaining === 1 ? 'día restante' : 'días restantes'} · vence el ${formatDate(expiresOn)}`;
    } else if (credits > 0) {
        big = credits;
        caption = credits === 1 ? 'crédito disponible' : 'créditos disponibles';
    } else {
        big = '0';
        caption = expiresOn ? `Venció el ${formatDate(expiresOn)}. Renueva en recepción.` : 'Sin vigencia. Renueva en recepción.';
    }

    return (
        <Card className="space-y-4">
            <div className="flex items-center justify-between gap-3">
                <div className="min-w-0">
                    <p className="text-sm text-slate-500">Mi membresía</p>
                    {planName && <p className="truncate font-semibold">{planName}</p>}
                </div>
                <StatusBadge status={status} label={statusLabel} />
            </div>

            <div>
                <p className="text-4xl font-black tracking-tight">{big}</p>
                <p className="text-sm text-slate-600">{caption}</p>
                {byDate && credits > 0 && <p className="mt-1 text-sm text-slate-500">Además tienes {creditsLabel(credits)}.</p>}
            </div>

            <Button href={credentialUrl} size="lg">
                <QrCode className="size-6" aria-hidden="true" /> Ver mi credencial QR
            </Button>
        </Card>
    );
}
