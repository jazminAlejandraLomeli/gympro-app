import { useMemo, useState } from 'react';
import { Search, UserRound } from 'lucide-react';
import { Button, cx, Input } from '@/Components/ui';

const matches = (m, t) => !t || m.name.toLowerCase().includes(t) || (m.email ?? '').toLowerCase().includes(t);

function SearchBox({ value, onChange }) {
    return (
        <div className="relative">
            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
            <Input type="search" value={value} onChange={(e) => onChange(e.target.value)} placeholder="Buscar socio por nombre o correo" aria-label="Buscar socio" className="pl-9" />
        </div>
    );
}

/** Elige UN socio con buscador. */
export function MemberSelect({ members, value, onChange, locked = false }) {
    const [term, setTerm] = useState('');
    const selected = members.find((m) => m.id === value);
    const results = useMemo(() => members.filter((m) => matches(m, term.trim().toLowerCase())).slice(0, 8), [members, term]);

    if (selected) {
        return (
            <div className="flex items-center gap-3 rounded-xl bg-white px-3 py-2.5 ring-1 ring-slate-300">
                <span className="grid size-9 shrink-0 place-items-center rounded-full bg-primary text-on-primary">
                    <UserRound className="size-4" aria-hidden="true" />
                </span>
                <span className="min-w-0 flex-1">
                    <span className="block truncate font-semibold">{selected.name}</span>
                    <span className="block truncate text-xs text-slate-500">{selected.email}</span>
                </span>
                {!locked && (
                    <Button variant="ghost" size="sm" onClick={() => onChange(null)}>
                        Cambiar
                    </Button>
                )}
            </div>
        );
    }

    return (
        <div className="space-y-2">
            <SearchBox value={term} onChange={setTerm} />
            <ul className="divide-y divide-slate-100 rounded-xl bg-white ring-1 ring-slate-200">
                {results.map((m) => (
                    <li key={m.id}>
                        <button type="button" onClick={() => onChange(m.id)} className="flex min-h-12 w-full flex-col justify-center px-3 py-2 text-left hover:bg-slate-50">
                            <span className="truncate text-sm font-medium">{m.name}</span>
                            <span className="truncate text-xs text-slate-500">{m.email}</span>
                        </button>
                    </li>
                ))}
                {results.length === 0 && <li className="px-3 py-3 text-sm text-slate-500">No hay socios con ese nombre.</li>}
            </ul>
        </div>
    );
}

/** Elige VARIOS socios con buscador y casillas. */
export function MemberMultiSelect({ members, value, onChange }) {
    const [term, setTerm] = useState('');
    const results = useMemo(() => members.filter((m) => matches(m, term.trim().toLowerCase())), [members, term]);
    const toggle = (id) => onChange(value.includes(id) ? value.filter((x) => x !== id) : [...value, id]);

    return (
        <div className="space-y-2">
            <SearchBox value={term} onChange={setTerm} />
            <ul className="max-h-80 divide-y divide-slate-100 overflow-y-auto rounded-xl bg-white ring-1 ring-slate-200">
                {results.map((m) => {
                    const on = value.includes(m.id);
                    return (
                        <li key={m.id}>
                            <label className={cx('flex min-h-12 cursor-pointer items-center gap-3 px-3 py-2', on ? 'bg-slate-50' : 'hover:bg-slate-50')}>
                                <input type="checkbox" className="size-5 shrink-0 accent-current" checked={on} onChange={() => toggle(m.id)} />
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-sm font-medium">{m.name}</span>
                                    <span className="block truncate text-xs text-slate-500">{m.email}</span>
                                </span>
                            </label>
                        </li>
                    );
                })}
                {results.length === 0 && <li className="px-3 py-3 text-sm text-slate-500">No hay socios para mostrar.</li>}
            </ul>
        </div>
    );
}
