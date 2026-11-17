import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

createInertiaApp({
    // "Rutina · Mi Gym" — el nombre lo pone la marca del gimnasio
    title: (title, page) => {
        const brandName = page?.props?.brand?.name ?? 'GymPro';
        return title ? `${title} · ${brandName}` : brandName;
    },

    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });
        const page = pages[`./Pages/${name}.jsx`];
        if (!page) throw new Error(`Página Inertia no encontrada: ${name}`);
        return page;
    },

    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },

    // La barra de carga toma el color primario del gimnasio
    progress: {
        color: 'var(--brand-primary)',
    },
});
