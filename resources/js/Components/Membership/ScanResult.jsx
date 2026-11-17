import { useEffect } from 'react';
import { CheckCircle2, ShieldAlert, XCircle } from 'lucide-react';
import { formatDate } from '@/lib/format';
import { creditsLabel } from './validity';

/** Fecha local (no UTC) en formato YYYY-MM-DD. */
const TODAY = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};

/**
 * Resultado a pantalla completa: verde (permitido), rojo (vencido) o
 * gris oscuro (código inválido). Se cierra solo o al tocarlo.
 */
export default function ScanResult({ result, onClose, duration = 3500 }) {
    useEffect(() => {
        if (!result) return;
        const t = setTimeout(onClose, duration);
        return () => clearTimeout(t);
    }, [result, onClose, duration]);

    if (!result) return null;

    const granted = result.status === 'granted';
    const denied = result.status === 'denied';
    const bg = granted ? 'bg-green-600' : denied ? 'bg-red-600' : 'bg-slate-800';
    const Icon = granted ? CheckCircle2 : denied ? XCircle : ShieldAlert;
    const title = granted ? 'ACCESO PERMITIDO' : denied ? 'ACCESO DENEGADO' : 'CÓDIGO INVÁLIDO';
    const byDate = result.expires_on && result.expires_on >= TODAY();

    return (
        <button
            type="button"
            onClick={onClose}
            className={`fixed inset-0 z-50 flex flex-col items-center justify-center gap-4 px-6 text-center text-white ${bg}`}
            aria-live="assertive"
        >
            <Icon className="size-24 sm:size-32" strokeWidth={2.2} aria-hidden="true" />
            <p className="text-3xl font-black tracking-tight sm:text-5xl">{title}</p>
            {denied && <p className="text-xl font-bold sm:text-2xl">MEMBRESÍA VENCIDA</p>}

            {result.member && (
                <div className="mt-2 flex flex-col items-center gap-3">
                    <span className="grid size-20 place-items-center rounded-full bg-white/20 text-4xl font-bold">{result.member.initial}</span>
                    <p className="text-2xl font-semibold sm:text-3xl">{result.member.name}</p>
                </div>
            )}

            <div className="space-y-1 text-lg opacity-95">
                {granted && byDate && <p>Vigente hasta el {formatDate(result.expires_on)}</p>}
                {granted && !byDate && result.credits !== null && <p>{creditsLabel(result.credits)} restantes</p>}
                {denied && result.expires_on && <p>Venció el {formatDate(result.expires_on)}</p>}
                {!granted && !denied && <p>{result.message}</p>}
                {granted && <p className="font-medium">{result.message}</p>}
            </div>

            {result.already_registered && (
                <span className="rounded-full bg-white/20 px-4 py-1.5 text-sm font-semibold">Ya registrado · no se descontó crédito</span>
            )}

            <span className="absolute bottom-8 text-sm opacity-80">Toca para continuar</span>
        </button>
    );
}
