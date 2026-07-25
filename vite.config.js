import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

// No remote font plugin: the demo must build with no network access.
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        // 5174, not the Vite default: keeps the demo off a port other local
        // projects commonly hold. Compose maps it 1:1 so the hot file URL works.
        port: 5174,
        strictPort: true,
        hmr: {
            host: 'localhost',
            clientPort: 5174,
        },
        watch: {
            usePolling: true,
            ignored: ['**/storage/framework/views/**', '**/vendor/**'],
        },
    },
});
