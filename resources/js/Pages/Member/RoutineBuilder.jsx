import { ArrowLeft } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui';
import RoutineBuilder from '@/Components/Training/RoutineBuilder';

/** El socio arma (o edita) su propia rutina. */
export default function MemberRoutineBuilder({ mode, routine, initial, catalog, muscleGroups, today }) {
    const isCreate = mode === 'create';

    return (
        <AppLayout title={isCreate ? 'Crear mi rutina' : 'Editar mi rutina'}>
            <div>
                <Button href={isCreate ? '/app/rutina' : `/app/rutina/${routine.id}`} variant="ghost" size="sm" className="-ml-2">
                    <ArrowLeft className="size-4" aria-hidden="true" /> {isCreate ? 'Mi rutina' : 'Volver'}
                </Button>
                <h1 className="mt-1 text-2xl font-bold">{isCreate ? 'Crear mi rutina' : 'Editar mi rutina'}</h1>
                {isCreate && <p className="text-sm text-slate-500">Al guardarla queda como tu rutina de hoy o de la semana.</p>}
            </div>

            <RoutineBuilder
                audience="member"
                initial={initial}
                submitUrl={isCreate ? '/app/rutina' : `/app/rutina/${routine.id}`}
                method={isCreate ? 'post' : 'put'}
                catalog={catalog}
                muscleGroups={muscleGroups}
                lockType={!isCreate}
                today={today}
                submitLabel={isCreate ? 'Guardar y usar' : 'Guardar cambios'}
            />
        </AppLayout>
    );
}
