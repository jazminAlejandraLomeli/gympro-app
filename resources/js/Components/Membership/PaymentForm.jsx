import { useForm } from '@inertiajs/react';
import { Button, Field, Input, cx } from '@/Components/ui';

const PRESETS = [
    { label: '1 mes', months_added: 1, credits_added: 0 },
    { label: '3 meses', months_added: 3, credits_added: 0 },
    { label: '10 créditos', months_added: 0, credits_added: 10 },
];

/** Registrar pago / renovación (cobro en recepción, sin pasarela). */
export default function PaymentForm({ membershipId, methods }) {
    const form = useForm({ months_added: 1, credits_added: 0, amount: '', method: 'efectivo', concept: '' });

    const submit = (e) => {
        e.preventDefault();
        form.post(`/panel/socios/${membershipId}/pagos`, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    return (
        <form onSubmit={submit} className="space-y-4">
            <div className="flex flex-wrap gap-2">
                {PRESETS.map((p) => {
                    const active = Number(form.data.months_added) === p.months_added && Number(form.data.credits_added) === p.credits_added;
                    return (
                        <button
                            key={p.label}
                            type="button"
                            onClick={() => form.setData({ ...form.data, months_added: p.months_added, credits_added: p.credits_added })}
                            className={cx(
                                'rounded-full px-4 py-2 text-sm font-semibold ring-1',
                                active ? 'bg-primary text-on-primary ring-transparent' : 'bg-white text-slate-700 ring-slate-300',
                            )}
                        >
                            {p.label}
                        </button>
                    );
                })}
            </div>

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

            <Field label="Concepto (opcional)" htmlFor="concept" error={form.errors.concept}>
                <Input id="concept" maxLength={120} placeholder="Se arma solo si lo dejas vacío" value={form.data.concept} onChange={(e) => form.setData('concept', e.target.value)} />
            </Field>

            <Button type="submit" size="lg" disabled={form.processing}>
                {form.processing ? 'Registrando…' : 'Registrar pago'}
            </Button>
        </form>
    );
}
