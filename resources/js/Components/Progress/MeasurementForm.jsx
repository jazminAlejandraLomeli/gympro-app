import { useForm } from '@inertiajs/react';
import { Button, Field, Input, Textarea } from '@/Components/ui';

const ORDER = ['weight_kg', 'body_fat_pct', 'waist_cm', 'chest_cm', 'arm_cm', 'leg_cm'];

/**
 * Formulario de valoración física. `action` = URL del POST
 * ('/app/progreso/medidas' o '/panel/valoraciones/{id}').
 */
export default function MeasurementForm({ action, metrics, today, submitLabel = 'Guardar medidas' }) {
    const byKey = Object.fromEntries(metrics.map((m) => [m.key, m]));
    const form = useForm({
        measured_on: today,
        weight_kg: '',
        body_fat_pct: '',
        waist_cm: '',
        chest_cm: '',
        arm_cm: '',
        leg_cm: '',
        notes: '',
    });

    const submit = (e) => {
        e.preventDefault();
        form.transform((data) => Object.fromEntries(Object.entries(data).map(([k, v]) => [k, v === '' ? null : v])));
        form.post(action, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    };

    // Un solo mensaje "captura al menos una medida" aunque falten todas
    const anyMissing = ORDER.every((k) => form.errors[k] === 'Captura al menos una medida.');

    return (
        <form onSubmit={submit} className="space-y-4" noValidate>
            <Field label="Fecha" htmlFor="measured_on" error={form.errors.measured_on}>
                <Input id="measured_on" type="date" max={today} value={form.data.measured_on} onChange={(e) => form.setData('measured_on', e.target.value)} />
            </Field>

            <div className="grid grid-cols-2 gap-3">
                {ORDER.map((key) => {
                    const m = byKey[key];
                    return (
                        <Field
                            key={key}
                            label={`${m.label} (${m.unit})`}
                            htmlFor={key}
                            error={anyMissing ? null : form.errors[key]}
                        >
                            <Input
                                id={key}
                                type="number"
                                inputMode="decimal"
                                step="0.1"
                                min={m.min}
                                max={m.max}
                                placeholder={`${m.min}–${m.max}`}
                                value={form.data[key]}
                                onChange={(e) => form.setData(key, e.target.value)}
                            />
                        </Field>
                    );
                })}
            </div>
            {anyMissing && <p className="text-sm text-red-600">Captura al menos una medida.</p>}

            <Field label="Notas (opcional)" htmlFor="notes" error={form.errors.notes}>
                <Textarea id="notes" maxLength={500} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
            </Field>

            <Button type="submit" size="lg" disabled={form.processing}>
                {form.processing ? 'Guardando…' : submitLabel}
            </Button>
        </form>
    );
}
