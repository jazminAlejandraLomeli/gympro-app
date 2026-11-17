import { useForm } from '@inertiajs/react';
import { Target } from 'lucide-react';
import { Button, Card, cx } from '@/Components/ui';

/** El socio elige cuántos días por semana quiere venir (1–7). */
export default function WeeklyGoalEditor({ goal, isCustom, defaultGoal }) {
    const form = useForm({ weekly_attendance_goal: goal });

    const submit = (e) => {
        e.preventDefault();
        form.put('/app/progreso/meta', { preserveScroll: true });
    };

    return (
        <Card>
            <form onSubmit={submit} className="space-y-3">
                <div className="flex items-start gap-3">
                    <div className="grid size-10 shrink-0 place-items-center rounded-xl bg-secondary text-on-secondary">
                        <Target className="size-5" aria-hidden="true" />
                    </div>
                    <div>
                        <h2 className="font-semibold">Mi meta semanal</h2>
                        <p className="text-sm text-slate-500">
                            ¿Cuántos días quieres venir por semana?{!isCustom && ` Recomendado por tu gimnasio: ${defaultGoal}.`}
                        </p>
                    </div>
                </div>

                <fieldset>
                    <legend className="sr-only">Días por semana</legend>
                    <div className="grid grid-cols-7 gap-1.5">
                        {[1, 2, 3, 4, 5, 6, 7].map((n) => (
                            <label key={n} className="cursor-pointer">
                                <input
                                    type="radio"
                                    name="weekly_attendance_goal"
                                    value={n}
                                    checked={Number(form.data.weekly_attendance_goal) === n}
                                    onChange={() => form.setData('weekly_attendance_goal', n)}
                                    className="peer sr-only"
                                />
                                <span
                                    className={cx(
                                        'grid aspect-square place-items-center rounded-xl text-base font-bold transition peer-focus-visible:ring-2 peer-focus-visible:ring-primary',
                                        Number(form.data.weekly_attendance_goal) === n ? 'bg-primary text-on-primary' : 'bg-slate-100 text-slate-600',
                                    )}
                                >
                                    {n}
                                </span>
                            </label>
                        ))}
                    </div>
                </fieldset>
                {form.errors.weekly_attendance_goal && <p className="text-sm text-red-600">{form.errors.weekly_attendance_goal}</p>}

                <Button type="submit" variant="secondary" className="w-full" disabled={form.processing || Number(form.data.weekly_attendance_goal) === goal}>
                    {form.processing ? 'Guardando…' : 'Guardar meta'}
                </Button>
            </form>
        </Card>
    );
}
