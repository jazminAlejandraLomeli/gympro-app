import { Head, usePage } from '@inertiajs/react';

const DAYS = ['L', 'M', 'X', 'J', 'V', 'S', 'D'];

/**
 * Página de prueba del stack: confirma que Inertia + React + Tailwind
 * funcionan y que los colores de marca blanca se aplican.
 */
export default function Welcome({ attendedDays = [] }) {
    const { brand } = usePage().props;

    return (
        <>
            <Head title="Inicio" />

            <header className="bg-secondary text-on-secondary">
                <div className="mx-auto flex max-w-md items-center gap-3 px-4 py-4">
                    {brand.logoUrl ? (
                        <img src={brand.logoUrl} alt="" className="size-10 rounded-full object-cover" />
                    ) : (
                        <div className="grid size-10 place-items-center rounded-full bg-primary font-bold text-on-primary">
                            {brand.name.charAt(0)}
                        </div>
                    )}
                    <h1 className="text-lg font-semibold">{brand.name}</h1>
                </div>
            </header>

            <main className="mx-auto max-w-md space-y-4 px-4 py-6">
                <section className="rounded-2xl bg-white p-5 shadow-sm">
                    <h2 className="text-base font-semibold">Tu semana</h2>
                    <ul className="mt-4 grid grid-cols-7 gap-2" aria-label="Asistencia de la semana">
                        {DAYS.map((day, i) => {
                            const attended = attendedDays.includes(i);
                            return (
                                <li
                                    key={day}
                                    className={`grid aspect-square place-items-center rounded-xl text-sm font-semibold ${
                                        attended ? 'bg-primary text-on-primary' : 'bg-slate-100 text-slate-400'
                                    }`}
                                    aria-label={`${day}: ${attended ? 'asististe' : 'sin asistencia'}`}
                                >
                                    {day}
                                </li>
                            );
                        })}
                    </ul>
                </section>

                <button
                    type="button"
                    className="w-full rounded-2xl bg-primary px-6 py-4 text-lg font-semibold text-on-primary shadow-sm active:scale-[0.98]"
                >
                    Ver mi credencial QR
                </button>
            </main>
        </>
    );
}
