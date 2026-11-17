import { Head, useForm, usePage } from '@inertiajs/react';
import { Button, Field, Input } from '@/Components/ui';

export default function Login() {
    const { brand, gym } = usePage().props;
    const form = useForm({ email: '', password: '', remember: false });

    const submit = (e) => {
        e.preventDefault();
        form.post('/login', { onFinish: () => form.reset('password') });
    };

    return (
        <>
            <Head title="Iniciar sesión" />
            <div className="flex min-h-dvh flex-col bg-secondary">
                <div className="flex flex-1 flex-col items-center justify-end px-6 pt-12 pb-8 text-center text-on-secondary">
                    {brand.logoUrl ? (
                        <img src={brand.logoUrl} alt="" className="size-20 rounded-full bg-white object-cover" />
                    ) : (
                        <div className="grid size-20 place-items-center rounded-full bg-primary text-3xl font-bold text-on-primary">
                            {brand.name.charAt(0)}
                        </div>
                    )}
                    <h1 className="mt-4 text-2xl font-bold">{brand.name}</h1>
                    <p className="mt-1 text-sm opacity-80">{gym ? 'Entra con el correo que registraste en recepción.' : 'Panel de administración'}</p>
                </div>

                <div className="rounded-t-3xl bg-white px-6 pt-8 pb-[calc(2rem+env(safe-area-inset-bottom))] sm:mx-auto sm:mb-12 sm:w-full sm:max-w-md sm:rounded-3xl">
                    <form onSubmit={submit} className="space-y-4" noValidate>
                        <Field label="Correo" htmlFor="email" error={form.errors.email}>
                            <Input
                                id="email"
                                type="email"
                                autoComplete="username"
                                inputMode="email"
                                autoFocus
                                value={form.data.email}
                                onChange={(e) => form.setData('email', e.target.value)}
                            />
                        </Field>
                        <Field label="Contraseña" htmlFor="password" error={form.errors.password}>
                            <Input
                                id="password"
                                type="password"
                                autoComplete="current-password"
                                value={form.data.password}
                                onChange={(e) => form.setData('password', e.target.value)}
                            />
                        </Field>
                        <label className="flex items-center gap-2 text-sm text-slate-600">
                            <input
                                type="checkbox"
                                className="size-5 rounded accent-[var(--brand-primary)]"
                                checked={form.data.remember}
                                onChange={(e) => form.setData('remember', e.target.checked)}
                            />
                            Mantener sesión iniciada
                        </label>
                        <Button type="submit" size="lg" disabled={form.processing}>
                            {form.processing ? 'Entrando…' : 'Entrar'}
                        </Button>
                    </form>
                </div>
            </div>
        </>
    );
}
