import { useForm } from '@inertiajs/react';
import { Megaphone, Sparkles } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import AnnouncementItem from '@/Components/Announcements/AnnouncementItem';
import { Button, Card, cx, Field, Input, PageHeader, Textarea } from '@/Components/ui';

const TYPE_ICONS = { operativo: Megaphone, promocion: Sparkles };
const TYPE_HINTS = {
    operativo: 'Horarios, cierres, mantenimiento. Se ve sobrio.',
    promocion: 'Ofertas y descuentos. Se destaca con tu color.',
};

/** Alta y edición de un aviso, con vista previa como la verá el socio. */
export default function Form({ announcement, types, defaults }) {
    const editing = Boolean(announcement);
    const form = useForm({
        type: announcement?.type ?? 'operativo',
        title: announcement?.title ?? '',
        body: announcement?.body ?? '',
        starts_at: announcement?.starts_at_local ?? defaults?.starts_at ?? '',
        expires_at: announcement?.expires_at_local ?? '',
        pinned: announcement?.pinned ?? false,
    });

    const submit = (e) => {
        e.preventDefault();
        if (editing) form.put(`/panel/avisos/${announcement.id}`, { preserveScroll: true });
        else form.post('/panel/avisos', { preserveScroll: true });
    };

    return (
        <AppLayout title={editing ? 'Editar aviso' : 'Nuevo aviso'}>
            <PageHeader
                title={editing ? 'Editar aviso' : 'Nuevo aviso'}
                description="Se publica solo en la fecha de inicio y desaparece al expirar."
            />

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)]">
                <Card>
                    <form onSubmit={submit} className="space-y-5">
                        <fieldset>
                            <legend className="mb-1.5 block text-sm font-medium text-slate-700">Tipo</legend>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {types.map((t) => {
                                    const TypeIcon = TYPE_ICONS[t.value];
                                    const selected = form.data.type === t.value;
                                    return (
                                        <label
                                            key={t.value}
                                            className={cx(
                                                'flex cursor-pointer items-start gap-3 rounded-xl p-3 ring-1',
                                                selected ? 'ring-2 ring-primary' : 'ring-slate-200',
                                            )}
                                        >
                                            <input
                                                type="radio"
                                                name="type"
                                                value={t.value}
                                                checked={selected}
                                                onChange={() => form.setData('type', t.value)}
                                                className="sr-only"
                                            />
                                            <TypeIcon className="mt-0.5 size-5 shrink-0" aria-hidden="true" />
                                            <span>
                                                <span className="block text-sm font-semibold">{t.label}</span>
                                                <span className="block text-xs text-slate-500">{TYPE_HINTS[t.value]}</span>
                                            </span>
                                        </label>
                                    );
                                })}
                            </div>
                            {form.errors.type && <p className="mt-1 text-sm text-red-600">{form.errors.type}</p>}
                        </fieldset>

                        <Field label="Título" htmlFor="title" error={form.errors.title}>
                            <Input id="title" value={form.data.title} maxLength={120} onChange={(e) => form.setData('title', e.target.value)} />
                        </Field>

                        <Field label="Contenido" htmlFor="body" error={form.errors.body} hint={`${form.data.body.length}/2000`}>
                            <Textarea id="body" value={form.data.body} maxLength={2000} rows={4} onChange={(e) => form.setData('body', e.target.value)} />
                        </Field>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Se publica desde" htmlFor="starts_at" error={form.errors.starts_at}>
                                <Input
                                    id="starts_at"
                                    type="datetime-local"
                                    value={form.data.starts_at}
                                    onChange={(e) => form.setData('starts_at', e.target.value)}
                                />
                            </Field>
                            <Field label="Expira" htmlFor="expires_at" error={form.errors.expires_at} hint="Déjalo vacío para que no expire.">
                                <Input
                                    id="expires_at"
                                    type="datetime-local"
                                    value={form.data.expires_at ?? ''}
                                    min={form.data.starts_at || undefined}
                                    onChange={(e) => form.setData('expires_at', e.target.value)}
                                />
                            </Field>
                        </div>

                        <label className="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" checked={form.data.pinned} onChange={(e) => form.setData('pinned', e.target.checked)} />
                            Fijar arriba de los demás avisos
                        </label>

                        <div className="flex flex-wrap gap-2">
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Guardando…' : editing ? 'Guardar cambios' : 'Publicar aviso'}
                            </Button>
                            <Button variant="secondary" href="/panel/avisos">
                                Cancelar
                            </Button>
                        </div>
                    </form>
                </Card>

                <div className="space-y-2">
                    <p className="text-sm font-medium text-slate-500">Vista previa para el socio</p>
                    <div className="rounded-2xl bg-slate-50 p-3 ring-1 ring-slate-200">
                        <AnnouncementItem announcement={{ ...form.data, expires_at: form.data.expires_at || null }} />
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
