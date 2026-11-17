import { useEffect, useRef, useState } from 'react';
import { Clapperboard } from 'lucide-react';
import { cx } from '@/Components/ui';

/**
 * Clip en bucle, sin sonido. Solo se descarga cuando entra en pantalla
 * y se pausa al salir (ahorra datos y batería en el celular).
 */
export default function ExerciseClip({ src, title, className }) {
    const ref = useRef(null);
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        const el = ref.current;
        if (!el || !src) return;
        if (!('IntersectionObserver' in window)) {
            setVisible(true);
            return;
        }
        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry.isIntersecting) {
                    setVisible(true);
                    el.play?.().catch(() => {});
                } else {
                    el.pause?.();
                }
            },
            { rootMargin: '200px 0px' },
        );
        observer.observe(el);
        return () => observer.disconnect();
    }, [src]);

    if (!src) {
        return (
            <div className={cx('grid place-items-center bg-slate-100 text-slate-300', className)} aria-hidden="true">
                <Clapperboard className="size-8" />
            </div>
        );
    }

    return (
        <video
            ref={ref}
            src={visible ? src : undefined}
            autoPlay
            muted
            loop
            playsInline
            preload="metadata"
            disablePictureInPicture
            aria-label={title ? `Demostración: ${title}` : 'Demostración del ejercicio'}
            className={cx('bg-slate-900 object-cover', className)}
        />
    );
}
