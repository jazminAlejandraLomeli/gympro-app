import { Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, EmptyState, PageHeader, Pagination, Stat, Table, Td, Th } from '@/Components/ui';
import { GymDueList } from '@/Components/Admin';
import RevenueChart from '@/Components/Admin/RevenueChart';
import { formatDate, formatMoney } from '@/lib/format';

/** Finanzas del SaaS: MRR, ingresos, vencimientos, proyección e historial. */
export default function Finance({ mrr, arr, collectedThisMonth, revenue, projection, expiring, overdue, payments }) {
    const last12 = revenue.reduce((sum, r) => sum + r.total, 0);

    return (
        <AppLayout title="Finanzas">
            <PageHeader title="Finanzas" description="Licencias SaaS: lo que entra cada mes y lo que viene." />

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Stat label="MRR" value={formatMoney(mrr)} icon="Wallet" hint="Cuota neta de gimnasios activos" />
                <Stat label="ARR" value={formatMoney(arr)} icon="TrendingUp" hint="MRR × 12" />
                <Stat label="Cobrado este mes" value={formatMoney(collectedThisMonth)} icon="Wallet" />
                <Stat label="Cobrado en 12 meses" value={formatMoney(last12)} icon="Activity" />
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-3">
                <Card className="min-w-0 lg:col-span-2">
                    <h2 className="mb-3 font-semibold">Ingresos por mes (últimos 12)</h2>
                    <RevenueChart data={revenue} />
                </Card>

                <Card>
                    <h2 className="font-semibold">Proyección</h2>
                    <p className="mb-3 text-xs text-slate-500">Si la red se mantiene como hoy (MRR × meses). «Ya pagado» es lo cubierto por adelantado.</p>
                    <ul className="divide-y divide-slate-100">
                        {projection.map((p) => (
                            <li key={p.months} className="flex items-center justify-between gap-3 py-3">
                                <span className="text-sm text-slate-600">{p.months} meses</span>
                                <span className="text-right">
                                    <span className="block font-bold">{formatMoney(p.projected)}</span>
                                    <span className="block text-xs text-slate-500">Ya pagado: {formatMoney(p.prepaid)}</span>
                                </span>
                            </li>
                        ))}
                    </ul>
                </Card>
            </div>

            <div className="mt-6 grid gap-6 md:grid-cols-2">
                <Card>
                    <h2 className="mb-2 font-semibold">Vencen en 7 días ({expiring.length})</h2>
                    <GymDueList gyms={expiring} empty="Nadie vence esta semana." />
                </Card>
                <Card>
                    <h2 className="mb-2 font-semibold">Vencidos / sin pagos ({overdue.length})</h2>
                    <GymDueList gyms={overdue} empty="Ningún gimnasio activo está vencido." />
                </Card>
            </div>

            <Card className="mt-6">
                <h2 className="mb-3 font-semibold">Historial de pagos</h2>
                {payments.data.length === 0 ? (
                    <EmptyState icon="Wallet" title="Aún no hay pagos">
                        Regístralos desde el detalle de cada gimnasio.
                    </EmptyState>
                ) : (
                    <>
                        <Table>
                            <thead>
                                <tr>
                                    <Th>Fecha</Th>
                                    <Th>Gimnasio</Th>
                                    <Th className="text-right">Monto</Th>
                                    <Th>Meses</Th>
                                    <Th>Cubre hasta</Th>
                                    <Th>Método</Th>
                                    <Th>Registró</Th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {payments.data.map((p) => (
                                    <tr key={p.id}>
                                        <Td>{formatDate(p.paid_at)}</Td>
                                        <Td>
                                            {p.gym ? (
                                                <Link href={'/admin/gimnasios/' + p.gym.id} className="font-medium hover:underline">
                                                    {p.gym.name}
                                                </Link>
                                            ) : (
                                                '—'
                                            )}
                                        </Td>
                                        <Td className="text-right font-semibold">{formatMoney(p.amount)}</Td>
                                        <Td>{p.months}</Td>
                                        <Td>{formatDate(p.covers_until)}</Td>
                                        <Td>{p.method}</Td>
                                        <Td className="text-slate-500">{p.recorded_by ?? '—'}</Td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                        <Pagination links={payments.links} />
                    </>
                )}
            </Card>
        </AppLayout>
    );
}
