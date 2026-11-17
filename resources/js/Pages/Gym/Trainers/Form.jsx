import { useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, Card, Field, Input, PageHeader } from '@/Components/ui';

/** Alta / edición de entrenador. */
export default function Form({ trainer }) {
    const editing = Boolean(trainer);
    const form = useForm({ name: trainer?.name ?? '', email: trainer?.email ?? '', password: '' });

    const submit = (e) => {
        e.preventDefault();
        if (editing) form.put(`/panel/entrenadores/${trainer.id}`);
        else form.post('/panel/entrenadores');
    };

    const title = editing ? 'Editar entrenador' : 'Nuevo entrenador';

    return (
        <AppLayout title={title}>
            <PageHeader title={title} />
            <Card className="max-w-xl">
                <form onSubmit={submit} className="space-y-4">
                    <Field label="Nombre" htmlFor="name" error={form.errors.name}>
                        <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} required />
                    </Field>
                    <Field label="Correo" htmlFor="email" error={form.errors.email}>
                        <Input id="email" type="email" inputMode="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} required />
                    </Field>
                    <Field
                        label={editing ? 'Nueva contraseña' : 'Contraseña'}
                        htmlFor="password"
                        error={form.errors.password}
                        hint={editing ? 'Déjala vacía para no cambiarla.' : 'Mínimo 8 caracteres.'}
                    >
                        <Input id="password" type="password" autoComplete="new-password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} required={!editing} />
                    </Field>
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Button type="submit" disabled={form.processing} className="flex-1">
                            {form.processing ? 'Guardando…' : 'Guardar'}
                        </Button>
                        <Button variant="secondary" href="/panel/entrenadores" className="flex-1">
                            Cancelar
                        </Button>
                    </div>
                </form>
            </Card>
        </AppLayout>
    );
}
