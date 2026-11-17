import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { fileURLToPath } from 'node:url';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600, 700],
                }),
            ],
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) },
        dedupe: ['react', 'react-dom'],
    },
    // Pre-empaqueta las librerías en `npm run dev`: evita que Vite las
    // descubra a medias y cargue dos copias de React ("Invalid hook call").
    optimizeDeps: {
        include: [
            'react',
            'react-dom',
            'react-dom/client',
            '@inertiajs/react',
            'recharts',
            'lucide-react',
            'html5-qrcode',
            'qrcode.react',
        ],
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
