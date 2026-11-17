import { Link, router } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, Card, PageHeader, Stat, Table, Td, Th } from '@/Components/ui';
import { formatBytes, GymStatusBadge, HealthCheck } from '@/Components/Admin';
import { formatDateTime, formatNumber } from '@/lib/format';

/** Salud del sistema: cifras de la red, servicios y almacenamiento. */
export default function System({ figures, checks, storage, storageTotal, storageCachedMinutes, checkedAt }) {
    const failing = checks.filter((c) => !c.ok).length;
    const max = Math.max(1, ...storage.map((s) => s.bytes));

    return (
        <AppLayout title="Sistema">
            <PageHeader
                title="Salud del sistema"
                description={'Revisado ' + formatDateTime(checkedAt) + (failing ? ' · ' + failing + ' servicio(s) con fallas' : ' · todo en orden')}
                actions={
                    <Button variant="secondary" onClick={() => router.reload({ preserveScroll: true })}>
                        <RefreshCw className="size-4" aria-hidden="true" /> Volver a revisar
                    </Button>
                }
            />

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Stat label="Gimnasios activos" value={formatNumber(figures.active_gyms)} icon="Building2" hint={figures.suspended_gyms + ' suspendido(s)'} />
                <Stat label="Socios en la red" value={formatNumber(figures.members)} icon="Users" />
                <Stat label="Escaneos QR hoy" value={formatNumber(figures.scans_today)} icon="ScanLine" />
                <Stat label="Multimedia total" value={formatBytes(storageTotal)} icon="Clapperboard" hint={'Se recalcula cada ' + storageCachedMinutes + ' min'} />
            </div>

            <Card className="mt-6">
                <h2 className="mb-3 font-semibold">Servicios</h2>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    {checks.map((c) => (
                        <HealthCheck key={c.key} check={c} />
                    ))}
                </div>
            </Card>

            <Card className="mt-6">
                <h2 className="mb-3 font-semibold">Almacenamiento multimedia por gimnasio</h2>
                <Table>
                    <thead>
                        <tr>
                            <Th>Gimnasio</Th>
                            <Th>Estado</Th>
                            <Th className="w-1/2">Uso</Th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {storage.map((s) => (
                            <tr key={s.id}>
                                <Td>
                                    <Link href={'/admin/gimnasios/' + s.id} className="font-medium hover:underline">
                                        {s.name}
                                    </Link>
                                </Td>
                                <Td>
                                    <GymStatusBadge status={s.status} />
                                </Td>
                                <Td>
                                    <div className="flex min-w-40 items-center gap-3">
                                        <div className="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                            <div className="h-full rounded-full bg-primary" style={{ width: Math.max(s.bytes ? 2 : 0, (s.bytes / max) * 100) + '%' }} />
                                        </div>
                                        <span className="w-20 shrink-0 text-right text-sm text-slate-600">{formatBytes(s.bytes)}</span>
                                    </div>
                                </Td>
                            </tr>
                        ))}
                    </tbody>
                </Table>
            </Card>
        </AppLayout>
    );
}
