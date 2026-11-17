import { Field, Input } from '@/Components/ui';

/** Datos personales del socio (alta y edición). */
export default function MemberFields({ form }) {
    const bind = (name) => ({ id: name, value: form.data[name] ?? '', onChange: (e) => form.setData(name, e.target.value) });

    return (
        <>
            <Field label="Nombre completo" htmlFor="name" error={form.errors.name}>
                <Input {...bind('name')} autoComplete="off" required />
            </Field>
            <Field label="Correo" htmlFor="email" error={form.errors.email} hint="Con este correo inicia sesión en la app.">
                <Input {...bind('email')} type="email" inputMode="email" autoComplete="off" required />
            </Field>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Teléfono" htmlFor="phone" error={form.errors.phone}>
                    <Input {...bind('phone')} type="tel" inputMode="tel" placeholder="55 1234 5678" />
                </Field>
                <Field label="Plan" htmlFor="plan_name" error={form.errors.plan_name}>
                    <Input {...bind('plan_name')} placeholder="Mensual, Trimestral, 10 clases…" />
                </Field>
            </div>
        </>
    );
}
