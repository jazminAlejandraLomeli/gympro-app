import { useForm } from '@inertiajs/react';
import { useEffect, useMemo } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import { Button, Card, Field, Input, PageHeader } from '@/Components/ui';

/** Marca blanca: nombre, colores y logotipo, con vista previa en vivo. */
export default function Branding({ settings }) {
    const form = useForm({
        brand_name: settings.brand_name,
        primary_color: settings.primary_color,
        secondary_color: settings.secondary_color,
        logo: null,
        remove_logo: false,
    });

    const logoPreview = useMemo(
        () => (form.data.logo ? URL.createObjectURL(form.data.logo) : form.data.remove_logo ? null : settings.logo_url),
        [form.data.logo, form.data.remove_logo, settings.logo_url],
    );
    useEffect(() => () => form.data.logo && URL.revokeObjectURL(logoPreview), [logoPreview]);

    const submit = (e) => {
        e.preventDefault();
        form.post('/panel/marca', { forceFormData: true, preserveScroll: true });
    };

    const ColorField = ({ name, label }) => (
        <Field label={label} htmlFor={name} error={form.errors[name]}>
            <div className="flex items-center gap-3">
                <input
                    type="color"
                    aria-label={label}
                    value={form.data[name]}
                    onChange={(e) => form.setData(name, e.target.value)}
                    className="h-11 w-14 cursor-pointer rounded-lg border border-slate-300"
                />
                <Input id={name} value={form.data[name]} onChange={(e) => form.setData(name, e.target.value)} maxLength={7} className="font-mono" />
            </div>
        </Field>
    );

    return (
        <AppLayout title="Mi marca">
            <PageHeader title="Mi marca" description="Así verán tus socios la app: logotipo, nombre y colores." />

            <div className="grid gap-6 lg:grid-cols-2">
                <Card>
                    <form onSubmit={submit} className="space-y-5">
                        <Field label="Nombre del gimnasio" htmlFor="brand_name" error={form.errors.brand_name}>
                            <Input id="brand_name" value={form.data.brand_name} onChange={(e) => form.setData('brand_name', e.target.value)} />
                        </Field>

                        <div className="grid gap-4 sm:grid-cols-2">
                            {ColorField({ name: 'primary_color', label: 'Color principal' })}
                            {ColorField({ name: 'secondary_color', label: 'Color secundario' })}
                        </div>

                        <Field label="Logotipo" htmlFor="logo" error={form.errors.logo} hint="PNG, JPG o WebP de hasta 1 MB. Mejor si es cuadrado.">
                            <input
                                id="logo"
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                onChange={(e) => form.setData({ ...form.data, logo: e.target.files[0] ?? null, remove_logo: false })}
                                className="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:font-semibold"
                            />
                        </Field>
                        {settings.logo_url && !form.data.logo && (
                            <label className="flex items-center gap-2 text-sm text-slate-600">
                                <input type="checkbox" checked={form.data.remove_logo} onChange={(e) => form.setData('remove_logo', e.target.checked)} />
                                Quitar logotipo actual
                            </label>
                        )}

                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Guardando…' : 'Guardar cambios'}
                        </Button>
                    </form>
                </Card>

                {/* Vista previa con los colores que se están editando */}
                <div
                    style={{ '--brand-primary': form.data.primary_color, '--brand-secondary': form.data.secondary_color }}
                    className="overflow-hidden rounded-2xl ring-1 ring-slate-200"
                    aria-label="Vista previa"
                >
                    <div className="flex items-center gap-3 bg-secondary p-4 text-on-secondary">
                        {logoPreview ? (
                            <img src={logoPreview} alt="" className="size-10 rounded-full bg-white object-cover" />
                        ) : (
                            <div className="grid size-10 place-items-center rounded-full bg-primary font-bold">{form.data.brand_name.charAt(0)}</div>
                        )}
                        <span className="font-semibold">{form.data.brand_name || 'Tu gimnasio'}</span>
                    </div>
                    <div className="space-y-3 bg-slate-50 p-4">
                        <div className="grid grid-cols-7 gap-1.5">
                            {['L', 'M', 'X', 'J', 'V', 'S', 'D'].map((d, i) => (
                                <span key={d} className={`grid aspect-square place-items-center rounded-lg text-xs font-semibold ${i % 2 === 0 ? 'bg-primary' : 'bg-slate-200 text-slate-400'}`}>
                                    {d}
                                </span>
                            ))}
                        </div>
                        <div className="rounded-xl bg-primary py-3 text-center font-semibold">Ver mi credencial QR</div>
                        <p className="text-xs text-slate-500">El color del texto (blanco o negro) se elige solo para que siempre se lea bien.</p>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
