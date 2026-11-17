import { Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, Card, PageHeader, Stat } from '@/Components/ui';
import { AlertList, GymDueList } from '@/Components/Admin';
import RevenueChart from '@/Components/Admin/RevenueChart';
import { formatMoney, formatNumber } from '@/lib/format';

/** Resumen ejecutivo del SaaS. */
export default function Dashboard({ figures, alerts, overdue, expiring, revenue }) {
    return (
        <AppLayout title="Resumen">
            <PageHeader
                title="Resumen"
                description="Cómo va la red de gimnasios hoy."
                actions={
                    <Button href="/admin/gimnasios" variant="secondary">
                        Ver gimnasios
                    </Button>
                }
            />

            <AlertList alerts={alerts} />

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Stat label="MRR" value={formatMoney(figures.mrr)} icon="Wallet" hint={'Cobrado este mes: ' + formatMoney(figures.collected_this_month)} />
                <Stat
                    label="Gimnasios activos"
                    value={formatNumber(figures.active_gyms)}
                    icon="Building2"
                    hint={figures.suspended_gyms ? figures.suspended_gyms + ' suspendido(s)' : 'Ninguno suspendido'}
                />
                <Stat label="Socios en la red" value={formatNumber(figures.members)} icon="Users" />
                <Stat label="Escaneos QR hoy" value={formatNumber(figures.scans_today)} icon="ScanLine" />
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-3">
                <Card className="min-w-0 lg:col-span-2">
                    <div className="mb-3 flex items-center justify-between gap-2">
                        <h2 className="font-semibold">Ingresos de los últimos 6 meses</h2>
                        <Link href="/admin/finanzas" className="text-sm font-medium text-slate-600 underline-offset-2 hover:underline">
                            Finanzas
                        </Link>
                    </div>
                    <RevenueChart data={revenue} height={220} />
                </Card>

                <Card>
                    <h2 className="mb-3 font-semibold">Operación</h2>
                    <dl className="space-y-3 text-sm">
                        <div className="flex justify-between gap-2">
                            <dt className="text-slate-500">Trabajos fallidos</dt>
                            <dd className={figures.failed_jobs ? 'font-semibold text-red-700' : 'font-semibold'}>{figures.failed_jobs}</dd>
                        </div>
                        <div className="flex justify-between gap-2">
                            <dt className="text-slate-500">Errores (24 h)</dt>
                            <dd className={figures.recent_errors ? 'font-semibold text-amber-700' : 'font-semibold'}>{figures.recent_errors}</dd>
                        </div>
                    </dl>
                    <div className="mt-4 flex flex-wrap gap-2">
                        <Button href="/admin/sistema" variant="secondary" size="sm">
                            Salud del sistema
                        </Button>
                        <Button href="/admin/errores" variant="secondary" size="sm">
                            Errores
                        </Button>
                    </div>
                </Card>
            </div>

            <div className="mt-6 grid gap-6 md:grid-cols-2">
                <Card>
                    <h2 className="mb-2 font-semibold">Vencidos</h2>
                    <GymDueList gyms={overdue} empty="Ningún gimnasio activo tiene la licencia vencida." />
                </Card>
                <Card>
                    <h2 className="mb-2 font-semibold">Por vencer (7 días)</h2>
                    <GymDueList gyms={expiring} empty="Nadie vence esta semana." />
                </Card>
            </div>
        </AppLayout>
    );
}
