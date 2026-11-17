import { useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, Card, Field, Input, PageHeader, cx } from '@/Components/ui';
import MemberFields from '@/Components/Membership/MemberFields';

/** Alta de socio: crea su usuario, su membresía y (opcional) el primer pago. */
export default function Create({ methods }) {
    const form = useForm({
        name: '',
        email: '',
        phone: '',
        plan_name: 'Mensual',
        months_added: 1,
        credits_added: 0,
        amount: '',
        method: 'efectivo',
    });

    const submit = (e) => {
        e.preventDefault();
        form.post('/panel/socios');
    };

    return (
        <AppLayout title="Nuevo socio">
            <PageHeader title="Nuevo socio" description="Se generará una contraseña inicial que verás una sola vez." />

            <form onSubmit={submit} className="grid gap-6 lg:grid-cols-2">
                <Card className="space-y-4">
                    <h2 className="font-semibold">Datos del socio</h2>
                    <MemberFields form={form} />
                </Card>

                <Card className="space-y-4">
                    <h2 className="font-semibold">Primer pago</h2>
                    <p className="text-sm text-slate-500">Deja meses y créditos en 0 si todavía no paga.</p>
                    <div className="grid grid-cols-2 gap-3">
                        <Field label="Meses" htmlFor="months_added" error={form.errors.months_added}>
                            <Input id="months_added" type="number" inputMode="numeric" min="0" max="24" value={form.data.months_added} onChange={(e) => form.setData('months_added', e.target.value)} />
                        </Field>
                        <Field label="Créditos" htmlFor="credits_added" error={form.errors.credits_added}>
                            <Input id="credits_added" type="number" inputMode="numeric" min="0" max="500" value={form.data.credits_added} onChange={(e) => form.setData('credits_added', e.target.value)} />
                        </Field>
                    </div>
                    <Field label="Monto cobrado (MXN)" htmlFor="amount" error={form.errors.amount}>
                        <Input id="amount" type="number" inputMode="decimal" min="0" step="0.01" placeholder="0.00" value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value)} />
                    </Field>
                    <Field label="Forma de pago" error={form.errors.method}>
                        <div className="grid grid-cols-3 gap-2">
                            {Object.entries(methods).map(([value, label]) => (
                                <button
                                    key={value}
                                    type="button"
                                    onClick={() => form.setData('method', value)}
                                    aria-pressed={form.data.method === value}
                                    className={cx(
                                        'rounded-xl px-2 py-3 text-sm font-semibold ring-1',
                                        form.data.method === value ? 'bg-secondary text-on-secondary ring-transparent' : 'bg-white text-slate-700 ring-slate-300',
                                    )}
                                >
                                    {label}
                                </button>
                            ))}
                        </div>
                    </Field>

                    <Button type="submit" size="lg" disabled={form.processing}>
                        {form.processing ? 'Registrando…' : 'Registrar socio'}
                    </Button>
                </Card>
            </form>
        </AppLayout>
    );
}
