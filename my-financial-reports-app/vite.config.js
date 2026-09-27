import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/reports/index.js',
                'resources/css/reports.css',
            ],
            refresh: true,
        }),
    ],
});