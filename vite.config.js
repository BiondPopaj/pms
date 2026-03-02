import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/js'),
            '~': resolve(__dirname, 'resources'),
        },
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    'vendor-vue': ['vue', '@inertiajs/vue3', 'pinia'],
                    'vendor-ui': ['@headlessui/vue', '@heroicons/vue', '@vueuse/core'],
                    'vendor-calendar': [
                        '@fullcalendar/core',
                        '@fullcalendar/vue3',
                        '@fullcalendar/daygrid',
                        '@fullcalendar/timegrid',
                        '@fullcalendar/interaction',
                        '@fullcalendar/resource',
                        '@fullcalendar/resource-timeline',
                    ],
                    'vendor-charts': ['chart.js', 'vue-chartjs'],
                    'vendor-forms': ['vee-validate', 'yup'],
                },
            },
        },
        chunkSizeWarningLimit: 1000,
        sourcemap: false,
        minify: 'esbuild',
    },
    server: {
        host: '0.0.0.0',
        hmr: {
            host: 'localhost',
        },
    },
    optimizeDeps: {
        include: [
            'vue',
            '@inertiajs/vue3',
            '@headlessui/vue',
            '@heroicons/vue/24/outline',
            '@heroicons/vue/24/solid',
            'axios',
            'dayjs',
        ],
    },
});
