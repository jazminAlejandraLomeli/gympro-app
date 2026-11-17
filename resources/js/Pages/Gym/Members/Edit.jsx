import { useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, Card, Field, Input, PageHeader } from '@/Components/ui';
import MemberFields from '@/Components/Membership/MemberFields';

/** Edición de datos y ajustes manuales de vigencia / créditos. */
export default function Edit({ member }) {
    const form = useForm({
        name: member.name,
        email: member.email,
        phone: member.phone ?? '',
        plan_name: member.plan_name ?? '',
        expires_on: member.expires_on ?? '',
        credits: member.credits,
    });

    const submit = (e) => {
        e.preventDefault();
        form.put(`/panel/socios/${member.id}`);
    };

    return (
        <AppLayout title={`Editar · ${member.name}`}>
            <PageHeader title="Editar socio" description={member.name} />

            <form onSubmit={submit} className="grid gap-6 lg:grid-cols-2">
                <Card className="space-y-4">
                    <MemberFields form={form} />
                </Card>

                <Card className="space-y-4">
                    <h2 className="font-semibold">Ajuste manual</h2>
                    <p className="text-sm text-slate-500">Úsalo solo para corregir errores. Para renovar, registra un pago en la ficha del socio.</p>
                    <div className="grid grid-cols-2 gap-3">
                        <Field label="Vigente hasta" htmlFor="expires_on" error={form.errors.expires_on}>
                            <Input id="expires_on" type="date" value={form.data.expires_on} onChange={(e) => form.setData('expires_on', e.target.value)} />
                        </Field>
                        <Field label="Créditos" htmlFor="credits" error={form.errors.credits}>
                            <Input id="credits" type="number" inputMode="numeric" min="0" value={form.data.credits} onChange={(e) => form.setData('credits', e.target.value)} />
                        </Field>
                    </div>
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Button type="submit" disabled={form.processing} className="flex-1">
                            {form.processing ? 'Guardando…' : 'Guardar cambios'}
                        </Button>
                        <Button variant="secondary" href={`/panel/socios/${member.id}`} className="flex-1">
                            Cancelar
                        </Button>
                    </div>
                </Card>
            </form>
        </AppLayout>
    );
}
