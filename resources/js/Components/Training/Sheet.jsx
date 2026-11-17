import { useEffect, useId } from 'react';
import { X } from 'lucide-react';
import { cx } from '@/Components/ui';

/**
 * Panel encima de la página. En móvil ocupa toda la pantalla; en
 * escritorio es un panel lateral (size="md") o una ventana grande
 * centrada (size="wide"). Cierra con Esc o tocando el fondo.
 */
export default function Sheet({ open, onClose, title, description, children, footer, size = 'md' }) {
    const titleId = useId();

    useEffect(() => {
        if (!open) return;
        const onKey = (e) => e.key === 'Escape' && onClose();
        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        window.addEventListener('keydown', onKey);
        return () => {
            document.body.style.overflow = previous;
            window.removeEventListener('keydown', onKey);
        };
    }, [open, onClose]);

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-40" role="dialog" aria-modal="true" aria-labelledby={titleId}>
            <div className="absolute inset-0 bg-black/40" onClick={onClose} aria-hidden="true" />
            <div
                className={cx(
                    'absolute inset-0 flex flex-col bg-slate-50 shadow-xl',
                    size === 'wide' ? 'sm:inset-6 sm:mx-auto sm:max-w-4xl sm:overflow-hidden sm:rounded-2xl' : 'sm:left-auto sm:w-full sm:max-w-lg',
                )}
            >
                <header className="flex items-start justify-between gap-3 border-b border-slate-200 bg-white px-4 py-3">
                    <div className="min-w-0 pt-1">
                        <h2 id={titleId} className="text-lg leading-tight font-bold">
                            {title}
                        </h2>
                        {description && <p className="mt-0.5 text-sm text-slate-500">{description}</p>}
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="grid size-11 shrink-0 place-items-center rounded-xl text-slate-600 hover:bg-slate-100"
                        aria-label="Cerrar"
                    >
                        <X className="size-5" aria-hidden="true" />
                    </button>
                </header>
                <div className="min-h-0 flex-1 overflow-y-auto px-4 py-4">{children}</div>
                {footer && (
                    <footer className="border-t border-slate-200 bg-white px-4 pt-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]">{footer}</footer>
                )}
            </div>
        </div>
    );
}
