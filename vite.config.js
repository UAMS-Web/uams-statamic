import { defineConfig, loadEnv } from 'vite'; // `loadEnv` added by studio1902/statamic-peak
import path from 'path';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite'; // Tailwind CSS for styling the control panel // Front end styling is handled by the UAMS 2020 theme CSS
import statamic from '@statamic/cms/vite-plugin'; // // Critical for building fieldtypes // Statamic 6: Vue 3 CP build (replaces @vitejs/plugin-vue2)

/**
 * Original structure of `export default defineConfig() before studio1902/statamic-peak:
 *
 * export default defineConfig({
 *     plugins: [
 *         laravel({
 *             input: [
 *                 'resources/css/site.css',
 *                 'resources/js/site.js',
 *                 'resources/css/cp.css', // Critical for building fieldtypes
 *                 'resources/js/cp.js' // Critical for building fieldtypes
 *             ],
 *             refresh: true,
 *         }),
 *         tailwindcss(),
 *         vue2() // Critical for building fieldtypes
 *     ],
 * });
 */


export default defineConfig(({ command, mode }) => {
    const env = loadEnv(mode, process.cwd(), '') // Added by studio1902/statamic-peak
return {
        css: {
            preprocessorOptions: {
                // Applied when processing .scss files (Vite uses this key for .scss)
                scss: {
                    // Include node_modules in the SASS include paths
                    includePaths: [path.resolve(__dirname, 'node_modules')],

                    // Silence @import deprecation until full migration to @use/@forward
                    silenceDeprecations: ['import'],

                    // Suppress deprecation warnings from dependencies (e.g., Bootstrap)
                    quietDeps: true,
                },
            },
        },
        resolve: {
            alias: {
                // Stable root for asset URLs in SCSS/CSS (e.g. url('@resources/svg/uams_2020/...')).
                '@resources': path.resolve(__dirname, 'resources'),
            },
        },
        build: {
            cssMinify: 'esbuild', // `esbuild` minifies CSS better than `lightningcss`
            rollupOptions: {
                output: {
                    manualChunks(id) {
                        const normalizedId = id.split(path.sep).join('/');
                        if (! normalizedId.includes('node_modules')) {
                            return;
                        }

                        return normalizedId.split('node_modules/')[1].split('/')[0].toString();
                    },
                },
            },
        }, // `build` added by studio1902/statamic-peak
        // Pre-bundle every bare-import dep used by the front-end entries so Vite
        // doesn't discover them lazily on first navigation and trigger a
        // re-optimization pass (which 504s any in-flight requests against the
        // old hash). Strings must match the `import` specifiers exactly — the
        // optimizer cache is keyed by import path, not package name. When adding
        // a new bare-import to a front-end entry, add it here in the same commit.
        optimizeDeps: {
            include: [
                '@alpinejs/csp', // CSP build of Alpine — the specifier the front-end entries import (UAMS-Web/uams-statamic-archive#1859)
                'leaflet', // front-end map viewer (resources/js/map-viewer.js)
                'dompurify', // alert-banner feed sanitization (resources/js/uams_2020/uamsalert.js)
            ],
        },
        plugins: [
            statamic(),
            laravel({
                input: [
                    // Base Statamic Assets
                        // Base Tailwind CSS
                            // 'resources/css/tailwind/site.css', // Hidden in favor of the compiled UAMS 2020 theme CSS
                        // Base Statamic JS
                            'resources/js/site.js',
                    // UAMS 2020 WordPress Theme Assets
                        // CSS
                            'resources/scss/bootstrap/uamswp_people_showcase/global.scss', // UAMSWP People Showcase
                            'resources/scss/bootstrap/uamswp_people_showcase/theme-card.scss', // UAMSWP People Showcase card theme
                            'resources/scss/bootstrap/uams_2020/wp-blocks.scss', // Live wp-block-file/embed remnant (UAMS-Web/uams-statamic-archive#1341)
                            'resources/scss/bootstrap/uamswp_logo_list/app.scss', // UAMSWP Logo List
                            'resources/scss/bootstrap/uamswp_find_a_doc/app.scss', // UAMSWP Find-a-Doc
                            'resources/scss/bootstrap/uams_2020/app.scss', // Main UAMS 2020 theme
                            'resources/scss/bootstrap/uams_2020/uamsalert.scss', // UAMS Alert banner
                        // JS — Vite bundles theme sources in the same concat order as the WP theme
                        // `gulpfile.js` (`paths.scripts.src` + `paths.fontawesome.src`); see
                        // `uams-2020-theme-entry.js` and `uams-2020-fontawesome-entry.js`.
                            'resources/js/uams_2020/uams-2020-theme-entry.js',
                            'resources/js/uams_2020/uams-2020-fontawesome-entry.js',
                            'resources/js/uams_2020/uamsalert.js', // UAMS alert banner (data feed)
                    // Control Panel Assets
                    // Critical for building fieldtypes
                    // https://statamic.dev/extending/control-panel#adding-css-and-js-assets
                        'resources/css/tailwind/cp.css',
                        'resources/js/cp.js'
                ],
                refresh: true
            }),
            tailwindcss(), // Tailwind CSS for styling the control panel // Front end styling is handled by the UAMS 2020 theme CSS
        ],
        server: {
            open: env.APP_URL,
            host: 'uams-statamic.test', // Serve Vite on the same domain
            port: 5173,
            hmr: {
                host: 'uams-statamic.test',
                port: 5173
            }
        } // `server` added by studio1902/statamic-peak
    }
});
