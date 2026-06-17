import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';
import os from 'os';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
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
        },
    },
    server: {
        // Exclure le dossier whatsapp-service du watcher Vite.
        // Sans ça, les fichiers du profil Edge/Chromium créés par Puppeteer
        // (SVG, CSS, binaires) déclenchent des rechargements HMR intempestifs.
        watch: {
            ignored: [
                '**/whatsapp-service/**',
                `${os.homedir().replace(/\\/g, '/')}/.simplon-whatsapp-auth/**`,
            ],
        },
        proxy: {
            '/storage': {
                target: 'http://127.0.0.1:8000',
                changeOrigin: true,
            },
        },
    },
});
