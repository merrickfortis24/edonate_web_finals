import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/adminlte.css',

                'resources/js/app.js',
                'resources/js/adminlte.js',
            ],
            publicDirectory: '../public_html',
            buildDirectory: 'build',
            refresh: true,
        }),

        tailwindcss(),
    ],

    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },

    build: {
        emptyOutDir: true,
    },
});
