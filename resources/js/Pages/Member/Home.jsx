import { usePage } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

/** Inicio del socio. Las tarjetas de cada módulo se integran aquí. */
export default function Home() {
    const { auth } = usePage().props;

    return (
        <AppLayout title="Inicio">
            <h1 className="text-2xl font-bold">Hola, {auth.user.name.split(' ')[0]}</h1>
        </AppLayout>
    );
}
