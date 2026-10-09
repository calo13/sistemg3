import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig(({ command }) => ({
    // Keep font and CSS asset URLs valid in subdirectories and root deployments.
    base: command === 'build' ? './' : undefined,
    plugins: [
        laravel({
            input: ['resources/scss/app.scss', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    css: {
        preprocessorOptions: {
            scss: {
                // The official Sneat sources use Sass imports and Bootstrap functions.
                silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'if-function'],
                quietDeps: true,
            },
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
}));
