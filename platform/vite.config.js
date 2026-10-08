import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            // player.js is only loaded on game pages so catalog pages stay light.
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/player.js', 'resources/js/room.js', 'resources/js/admin.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    build: {
        target: 'es2020',
        cssCodeSplit: true,
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
