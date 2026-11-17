import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

// Carga diferida: cada página es su propio archivo JS (el socio no
// descarga el código del panel del super_admin).
const pages = import.meta.glob('./Pages/**/*.jsx');

createInertiaApp({
    // "Rutina · Mi Gym" — el nombre lo pone la marca del gimnasio
    title: (title, page) => {
        const brandName = page?.props?.brand?.name ?? 'GymPro';
        return title ? `${title} · ${brandName}` : brandName;
    },

    resolve: async (name) => {
        const load = pages[`./Pages/${name}.jsx`];
        if (!load) throw new Error(`Página Inertia no encontrada: ${name}`);
        return load();
    },

    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },

    // La barra de carga toma el color primario del gimnasio
    progress: {
        color: 'var(--brand-primary)',
    },
});
