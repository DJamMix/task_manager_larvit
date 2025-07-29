import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/custom-styles.css',
                'resources/css/welcome.css',
                'resources/js/welcome.js'
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
