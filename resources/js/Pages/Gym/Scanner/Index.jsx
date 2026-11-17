import { useCallback, useEffect, useRef, useState } from 'react';
import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode';
import { Camera, CameraOff, Search, UserCheck } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, Card, Input, PageHeader } from '@/Components/ui';
import ScanResult from '@/Components/Membership/ScanResult';
import StatusBadge from '@/Components/Membership/StatusBadge';
import { playTone, unlockAudio } from '@/Components/Membership/sound';
import { validitySummary } from '@/Components/Membership/validity';
import { getJson, postJson } from '@/lib/http';

const READER_ID = 'gp-qr-reader';
/** Milisegundos en los que se ignora el mismo código (evita lecturas repetidas). */
const SAME_CODE_COOLDOWN = 5000;

function errorResult(status) {
    const message =
        status === 429 ? 'Demasiados intentos. Espera unos segundos.' : status === 419 ? 'La sesión expiró. Recarga la página.' : 'No se pudo validar. Revisa la conexión.';
    return { status: 'invalid', message, member: null };
}

/** Escáner de credenciales QR + entrada manual. */
export default function Index() {
    const [result, setResult] = useState(null);
    const [cameraOn, setCameraOn] = useState(true);
    const [cameraError, setCameraError] = useState(null);
    const busy = useRef(false);
    const last = useRef({ code: null, at: 0 });

    const show = useCallback((data) => {
        setResult(data);
        playTone(data.status);
        if (navigator.vibrate) navigator.vibrate(data.status === 'granted' ? 80 : [120, 80, 120]);
    }, []);

    const close = useCallback(() => {
        setResult(null);
        busy.current = false;
    }, []);

    const onDecode = useCallback(
        async (code) => {
            const now = Date.now();
            if (busy.current) return;
            if (last.current.code === code && now - last.current.at < SAME_CODE_COOLDOWN) return;

            busy.current = true;
            last.current = { code, at: now };

            const { ok, status, data } = await postJson('/panel/asistencia/escanear', { payload: code });
            show(ok && data ? data : errorResult(status));
            // Mismo código: el contador corre desde que se cierra el resultado
            last.current = { code, at: Date.now() + 3500 };
        },
        [show],
    );

    // Cámara trasera con html5-qrcode
    useEffect(() => {
        if (!cameraOn) return;

        const scanner = new Html5Qrcode(READER_ID, { formatsToSupport: [Html5QrcodeSupportedFormats.QR_CODE], verbose: false });
        const config = {
            fps: 12,
            qrbox: (w, h) => {
                const size = Math.max(160, Math.floor(Math.min(w, h) * 0.7));
                return { width: size, height: size };
            },
        };

        setCameraError(null);
        const started = scanner
            .start({ facingMode: 'environment' }, config, (text) => onDecode(text), () => {})
            .catch(() => setCameraError('No se pudo abrir la cámara. Revisa los permisos del navegador (se requiere HTTPS).'));

        return () => {
            started
                .then(() => (scanner.isScanning ? scanner.stop() : null))
                .then(() => scanner.clear())
                .catch(() => {});
        };
    }, [cameraOn, onDecode]);

    return (
        <AppLayout title="Escáner QR">
            <div onPointerDown={unlockAudio}>
                <PageHeader
                    title="Escáner QR"
                    description="Apunta la cámara a la credencial del socio."
                    actions={
                        <Button variant="secondary" onClick={() => setCameraOn((v) => !v)}>
                            {cameraOn ? <CameraOff className="size-5" aria-hidden="true" /> : <Camera className="size-5" aria-hidden="true" />}
                            {cameraOn ? 'Apagar cámara' : 'Encender cámara'}
                        </Button>
                    }
                />

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card className="overflow-hidden p-0 sm:p-0">
                        {cameraOn ? (
                            <div id={READER_ID} className="aspect-square w-full bg-black [&_video]:h-full [&_video]:w-full [&_video]:object-cover" />
                        ) : (
                            <div className="grid aspect-square w-full place-items-center bg-slate-100 text-slate-500">Cámara apagada</div>
                        )}
                        {cameraError && <p className="p-4 text-sm text-red-600">{cameraError}</p>}
                    </Card>

                    <ManualEntry onResult={show} />
                </div>
            </div>

            <ScanResult result={result} onClose={close} />
        </AppLayout>
    );
}

/** Buscar socio y registrar su entrada sin QR. */
function ManualEntry({ onResult }) {
    const [q, setQ] = useState('');
    const [members, setMembers] = useState([]);
    const [loading, setLoading] = useState(false);
    const [sending, setSending] = useState(null);

    useEffect(() => {
        if (q.trim().length < 2) {
            setMembers([]);
            return;
        }
        let cancelled = false;
        const t = setTimeout(async () => {
            setLoading(true);
            const { ok, data } = await getJson(`/panel/escaner/buscar?q=${encodeURIComponent(q.trim())}`);
            if (!cancelled) {
                setMembers(ok ? data.data : []);
                setLoading(false);
            }
        }, 200);
        return () => {
            cancelled = true;
            clearTimeout(t);
        };
    }, [q]);

    const checkIn = async (m) => {
        setSending(m.user_id);
        const { ok, status, data } = await postJson('/panel/asistencia/manual', { user_id: m.user_id });
        setSending(null);
        onResult(ok && data ? data : errorResult(status));
        if (ok) setQ('');
    };

    return (
        <Card className="space-y-3">
            <h2 className="font-semibold">Entrada manual</h2>
            <div className="relative">
                <Search className="pointer-events-none absolute top-1/2 left-3.5 size-5 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                <Input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="Nombre, correo o teléfono" className="py-3 pl-11" aria-label="Buscar socio" />
            </div>

            {loading && <p className="text-sm text-slate-500">Buscando…</p>}
            {!loading && q.trim().length >= 2 && members.length === 0 && <p className="text-sm text-slate-500">Sin resultados.</p>}

            <ul className="divide-y divide-slate-100">
                {members.map((m) => (
                    <li key={m.id} className="flex flex-col gap-2 py-3 sm:flex-row sm:items-center">
                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <p className="truncate font-semibold">{m.name}</p>
                                <StatusBadge status={m.status} label={m.status_label} />
                            </div>
                            <p className="truncate text-sm text-slate-500">{validitySummary(m)}</p>
                        </div>
                        <Button onClick={() => checkIn(m)} disabled={sending !== null} className="w-full sm:w-auto">
                            <UserCheck className="size-5" aria-hidden="true" /> {sending === m.user_id ? 'Registrando…' : 'Registrar entrada'}
                        </Button>
                    </li>
                ))}
            </ul>
        </Card>
    );
}
