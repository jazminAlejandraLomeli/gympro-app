import { Link, usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, Icon, PageHeader, Stat } from '@/Components/ui';
import { formatNumber } from '@/lib/format';

export default function Dashboard({ stats }) {
    const { auth, nav } = usePage().props;
    const shortcuts = nav.filter((i) => !i.active);

    return (
        <AppLayout title="Inicio">
            <PageHeader title={`Hola, ${auth.user.name.split(' ')[0]}`} description={auth.user.roleLabel} />

            <div className="grid gap-3 sm:grid-cols-3">
                <Stat label="Socios" value={formatNumber(stats.members)} icon="Users" />
                <Stat label="Entrenadores" value={formatNumber(stats.trainers)} icon="UserCog" />
                <Stat label="Entradas hoy" value={formatNumber(stats.checkinsToday)} icon="ScanLine" />
            </div>

            <h2 className="mt-8 mb-3 text-sm font-semibold text-slate-500 uppercase">Accesos rápidos</h2>
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                {shortcuts.map((item) => (
                    <Link key={item.href} href={item.href}>
                        <Card className="flex h-full flex-col items-start gap-3 transition hover:ring-primary">
                            <span className="grid size-10 place-items-center rounded-xl bg-primary text-on-primary">
                                <Icon name={item.icon} />
                            </span>
                            <span className="font-semibold">{item.label}</span>
                        </Card>
                    </Link>
                ))}
            </div>
        </AppLayout>
    );
}
