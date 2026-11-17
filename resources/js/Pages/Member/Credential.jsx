import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { QRCodeSVG } from 'qrcode.react';
import { Sun } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, EmptyState } from '@/Components/ui';
import StatusBadge from '@/Components/Membership/StatusBadge';
import { creditsLabel, daysLabel } from '@/Components/Membership/validity';
import { formatDate } from '@/lib/format';

/** Mantiene la pantalla encendida mientras se muestra el QR (si el navegador lo permite). */
function useWakeLock() {
    useEffect(() => {
        let lock = null;
        const request = async () => {
            try {
                if (document.visibilityState === 'visible' && 'wakeLock' in navigator) lock = await navigator.wakeLock.request('screen');
            } catch {
                // no disponible
            }
        };
        request();
        document.addEventListener('visibilitychange', request);
        return () => {
            document.removeEventListener('visibilitychange', request);
            lock?.release?.().catch(() => {});
        };
    }, []);
}

/** Credencial QR del socio. Fondo blanco siempre, aunque la marca sea oscura. */
export default function Credential({ qr, memberName, membership }) {
    const { brand } = usePage().props;
    useWakeLock();

    return (
        <AppLayout title="Mi credencial">
            {!qr ? (
                <EmptyState icon="QrCode" title="Aún no tienes credencial">
                    Pide en recepción que registren tu membresía.
                </EmptyState>
            ) : (
                <>
                    <Card className="space-y-4 bg-white text-center">
                        <div className="flex items-center justify-center gap-3">
                            {brand.logoUrl ? (
                                <img src={brand.logoUrl} alt="" className="size-10 rounded-full object-cover" />
                            ) : (
                                <span className="grid size-10 place-items-center rounded-full bg-primary font-bold text-on-primary">{brand.name.charAt(0)}</span>
                            )}
                            <span className="truncate text-lg font-bold">{brand.name}</span>
                        </div>

                        <div className="mx-auto w-full max-w-[22rem] rounded-2xl bg-white p-2">
                            <QRCodeSVG
                                value={qr}
                                level="M"
                                marginSize={4}
                                size={512}
                                bgColor="#ffffff"
                                fgColor="#000000"
                                title={`Credencial de ${memberName}`}
                                className="block h-auto w-full"
                            />
                        </div>

                        <div className="space-y-2">
                            <p className="text-xl font-bold">{memberName}</p>
                            <StatusBadge status={membership.status} label={membership.statusLabel} />
                            <p className="text-sm text-slate-600">
                                {membership.daysRemaining !== null
                                    ? `Vigente hasta el ${formatDate(membership.expiresOn)} · ${daysLabel(membership.daysRemaining)}`
                                    : membership.expiresOn
                                      ? `Venció el ${formatDate(membership.expiresOn)}`
                                      : 'Sin vigencia por fecha'}
                            </p>
                            {membership.credits > 0 && <p className="text-sm text-slate-600">{creditsLabel(membership.credits)} disponibles</p>}
                        </div>
                    </Card>

                    <div className="flex items-start gap-3 rounded-2xl bg-white p-4 text-sm text-slate-600 ring-1 ring-slate-200/60">
                        <Sun className="size-5 shrink-0 text-amber-500" aria-hidden="true" />
                        <p>Sube el brillo de tu pantalla y acerca el celular al lector de recepción. No compartas capturas de tu código.</p>
                    </div>
                </>
            )}
        </AppLayout>
    );
}
