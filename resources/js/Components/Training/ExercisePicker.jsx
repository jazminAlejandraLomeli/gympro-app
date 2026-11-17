import { useEffect, useMemo, useState } from 'react';
import { Check, Search } from 'lucide-react';
import { Button, cx, Input } from '@/Components/ui';
import Chip from './Chip';
import ExerciseClip from './ExerciseClip';
import Sheet from './Sheet';

/**
 * Selector de ejercicios de la biblioteca: pantalla completa en el
 * celular, búsqueda, chips de grupo muscular y selección múltiple.
 * onAdd recibe los ejercicios en el orden en que se tocaron.
 */
export default function ExercisePicker({ open, onClose, catalog, muscleGroups, onAdd, targetLabel }) {
    const [term, setTerm] = useState('');
    const [group, setGroup] = useState('');
    const [selected, setSelected] = useState([]);

    useEffect(() => {
        if (open) setSelected([]);
    }, [open]);

    const byId = useMemo(() => Object.fromEntries(catalog.map((e) => [e.id, e])), [catalog]);
    const results = useMemo(() => {
        const t = term.trim().toLowerCase();
        return catalog.filter(
            (e) =>
                (!group || e.muscle_group === group) &&
                (!t || e.name.toLowerCase().includes(t) || (e.objective ?? '').toLowerCase().includes(t)),
        );
    }, [catalog, term, group]);

    const toggle = (id) => setSelected((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id]));
    const n = selected.length;

    const confirm = () => {
        onAdd(selected.map((id) => byId[id]).filter(Boolean));
        onClose();
    };

    return (
        <Sheet
            open={open}
            onClose={onClose}
            size="wide"
            title="Agregar ejercicios"
            description={targetLabel ? `A ${targetLabel}` : undefined}
            footer={
                <Button size="lg" onClick={confirm} disabled={n === 0}>
                    {n === 0 ? 'Elige uno o más ejercicios' : `Agregar (${n})`}
                </Button>
            }
        >
            <div className="space-y-3">
                <div className="relative">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                    <Input
                        type="search"
                        value={term}
                        onChange={(e) => setTerm(e.target.value)}
                        placeholder="Buscar por nombre u objetivo"
                        aria-label="Buscar ejercicio"
                        className="pl-9"
                    />
                </div>
                <div className="flex flex-wrap gap-1.5" role="group" aria-label="Filtrar por grupo muscular">
                    <Chip active={!group} onClick={() => setGroup('')}>
                        Todos
                    </Chip>
                    {muscleGroups.map((g) => (
                        <Chip key={g.value} active={group === g.value} onClick={() => setGroup(g.value)}>
                            {g.label}
                        </Chip>
                    ))}
                </div>

                {catalog.length === 0 ? (
                    <p className="rounded-2xl border-2 border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500">
                        La biblioteca de ejercicios está vacía. Pide a tu coach que agregue ejercicios.
                    </p>
                ) : results.length === 0 ? (
                    <p className="py-8 text-center text-sm text-slate-500">Sin resultados. Prueba con otra búsqueda.</p>
                ) : (
                    <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                        {results.map((e) => {
                            const order = selected.indexOf(e.id);
                            const isOn = order !== -1;
                            return (
                                <li key={e.id}>
                                    <button
                                        type="button"
                                        onClick={() => toggle(e.id)}
                                        aria-pressed={isOn}
                                        className={cx(
                                            'flex h-full w-full flex-col overflow-hidden rounded-2xl bg-white text-left shadow-sm ring-1 transition',
                                            isOn ? 'ring-2 ring-primary' : 'ring-slate-200 hover:ring-slate-300',
                                        )}
                                    >
                                        <span className="relative block">
                                            <ExerciseClip src={e.clip_url} title={e.name} className="aspect-video w-full" />
                                            {isOn && (
                                                <span className="absolute top-2 right-2 grid size-7 place-items-center rounded-full bg-primary text-xs font-bold text-on-primary">
                                                    {order + 1}
                                                </span>
                                            )}
                                        </span>
                                        <span className="flex flex-1 flex-col gap-0.5 p-2.5">
                                            <span className="text-sm leading-tight font-semibold">{e.name}</span>
                                            <span className="text-xs text-slate-500">{e.muscle_group_label}</span>
                                            {e.objective && <span className="line-clamp-2 text-xs text-slate-600">{e.objective}</span>}
                                            {!isOn && (
                                                <span className="mt-auto flex items-center gap-1 pt-1 text-xs font-medium text-slate-400">
                                                    <Check className="size-3.5" aria-hidden="true" /> Tocar para elegir
                                                </span>
                                            )}
                                        </span>
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </div>
        </Sheet>
    );
}
