import tailwindcss from '@tailwindcss/vite';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import statamic from '@statamic/cms/vite-plugin';

export default defineConfig({
    build: {
        // The CP bundle is a single-entry SPA (Statamic CP framework + Vue +
        // Leaflet + the custom fieldtypes) that minifies to ~1 MB (~278 kB
        // gzipped) — an expected, acceptable size for an authenticated,
        // low-traffic surface, not a regression. Raise Vite's default 500 kB
        // chunk-size advisory threshold so the benign warning stops reading as
        // a red flag on every `cp:build`; the headroom is deliberately small so
        // a genuine future size jump becomes visible again rather than lost in
        // standing noise (UAMS-Web/uams-statamic-archive#1114). Shrinking the bundle (manualChunks / lazy
        // Leaflet) is a separate, out-of-scope optimization.
        chunkSizeWarningLimit: 1200,
    },
    // Use a separate port from `vite.config.js` (5173) so site + CP dev
    // servers can run at the same time via `npm run dev:with-cp`.
    server: {
        host: 'uams-statamic.test',
        port: 5174,
        strictPort: true,
        hmr: {
            host: 'uams-statamic.test',
            port: 5174,
        },
    },
    plugins: [
        statamic(),
        laravel({
            input: [
                'resources/css/tailwind/cp.css',
                'resources/js/cp.js',
            ],
            hotFile: 'public/cp-hot',
            buildDirectory: 'vendor/app',
        }),
        tailwindcss(),
    ],
});
