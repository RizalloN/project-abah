import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/presentation/presentation.css',
                'resources/js/presentation/pres-engine.js',
            ],
            refresh: true,
        }),
    ],
});
