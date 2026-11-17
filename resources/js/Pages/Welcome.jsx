import { Head, usePage } from '@inertiajs/react';
import { Button } from '@/Components/ui';

/** Landing del dominio central (la de cada gimnasio redirige a su login). */
export default function Welcome() {
    const { brand, auth } = usePage().props;

    return (
        <>
            <Head title="Plataforma para gimnasios" />
            <div className="flex min-h-dvh flex-col bg-secondary text-on-secondary">
                <header className="mx-auto flex w-full max-w-5xl items-center justify-between px-6 py-5">
                    <span className="text-xl font-bold">{brand.name}</span>
                    <Button href={auth.user ? '/inicio' : '/login'} variant="secondary" size="sm">
                        {auth.user ? 'Ir al panel' : 'Iniciar sesión'}
                    </Button>
                </header>
                <main className="mx-auto flex w-full max-w-5xl flex-1 flex-col justify-center px-6 pb-20">
                    <h1 className="max-w-2xl text-4xl font-bold tracking-tight sm:text-5xl">Tu gimnasio, con tu marca, en el celular de tus socios.</h1>
                    <p className="mt-4 max-w-xl text-lg opacity-80">
                        Credencial QR, rutinas con video, rachas de asistencia y seguimiento físico. Todo con el logotipo y los colores de tu gimnasio.
                    </p>
                </main>
            </div>
        </>
    );
}
