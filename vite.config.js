import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import path from 'node:path';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    server: {
        host: process.env.VITE_DEV_SERVER_HOST ?? '0.0.0.0',
        port: Number(process.env.VITE_PORT ?? 5174),
        strictPort: true,
        hmr: {
            host: process.env.VITE_DEV_SERVER_HMR_HOST ?? 'localhost',
            port: Number(process.env.VITE_PORT ?? 5174),
        },
    },
});
