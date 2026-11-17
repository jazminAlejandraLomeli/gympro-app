import { router } from '@inertiajs/react';
import { Pencil, Trash2, UserPlus } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, Card, EmptyState, PageHeader } from '@/Components/ui';
import { formatDate } from '@/lib/format';

/** Entrenadores del gimnasio. */
export default function Index({ trainers }) {
    const destroy = (t) => {
        if (confirm(`¿Dar de baja a ${t.name}? Ya no podrá iniciar sesión.`)) {
            router.delete(`/panel/entrenadores/${t.id}`, { preserveScroll: true });
        }
    };

    return (
        <AppLayout title="Entrenadores">
            <PageHeader
                title="Entrenadores"
                description="Personal que arma rutinas y valoraciones."
                actions={
                    <Button href="/panel/entrenadores/nuevo">
                        <UserPlus className="size-5" aria-hidden="true" /> Nuevo entrenador
                    </Button>
                }
            />

            {trainers.length === 0 ? (
                <EmptyState icon="UserCog" title="Aún no hay entrenadores" />
            ) : (
                <Card className="p-0 sm:p-0">
                    <ul className="divide-y divide-slate-100">
                        {trainers.map((t) => (
                            <li key={t.id} className="flex items-center gap-3 px-4 py-3.5">
                                <span className="grid size-10 shrink-0 place-items-center rounded-full bg-secondary font-bold text-on-secondary">
                                    {t.name.charAt(0).toUpperCase()}
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="truncate font-semibold">{t.name}</p>
                                    <p className="truncate text-sm text-slate-500">{t.email}</p>
                                    <p className="text-xs text-slate-400">Desde {formatDate(t.created_at)}</p>
                                </div>
                                <Button variant="ghost" size="sm" href={`/panel/entrenadores/${t.id}/editar`} aria-label={`Editar a ${t.name}`}>
                                    <Pencil className="size-5" aria-hidden="true" />
                                </Button>
                                <Button variant="ghost" size="sm" onClick={() => destroy(t)} aria-label={`Dar de baja a ${t.name}`} className="text-red-600">
                                    <Trash2 className="size-5" aria-hidden="true" />
                                </Button>
                            </li>
                        ))}
                    </ul>
                </Card>
            )}
        </AppLayout>
    );
}
