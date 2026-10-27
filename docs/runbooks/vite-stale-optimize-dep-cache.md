# Runbook: Vite stale optimize-dep cache (504 → form fields invisible)

After certain dev-workflow events, the Vite optimize-dep cache under `node_modules/.vite/deps` goes stale, Vite returns **`504 Outdated Optimize Dep`** on each affected module, the [`resources/js/site.js`](../../resources/js/site.js) module-graph fails to evaluate, `Alpine.start()` never runs, and every page that depends on Alpine silently breaks.

The most visible symptom on this site is **public forms with no rendered fields** — the `<template x-if="$showField(...)">` wrappers in [`resources/views/vendor/statamic/forms/fields.antlers.html`](../../resources/views/vendor/statamic/forms/fields.antlers.html) never activate, so each field's `<div class="form-group">` stays inert inside the `<template>` element and never reaches the live DOM. The page shows the form's heading and Submit button but no inputs.

## Symptoms

- Public form (or any Alpine-driven UI) renders without expected fields.
- Browser DevTools → Console: one or more entries of `Failed to load resource: the server responded with a status of 504 (Outdated Optimize Dep)`.
- `typeof window.Alpine` → `'undefined'` in the console.
- The page HTML (curl / View Source) still contains the `<template x-if="...">` wrappers; they're just never activated.

## Cause

`vite` pre-bundles selected `node_modules` dependencies into `node_modules/.vite/deps/*.js` on first run and serves them with cache-busting hashes. When the underlying package on disk changes (via `npm ci`, lockfile bump, branch switch, `composer update` for packages that ship JS, etc.) but Vite is already running, the pre-bundled output and the on-disk source disagree. Vite detects the mismatch on the next module request and returns `504 Outdated Optimize Dep`. The browser then fails the dependent ES-module import (e.g. `import Alpine from '@alpinejs/csp'`), which cascades through [`site.js`](../../resources/js/site.js) and prevents `Alpine.start()` from running.

## Fix

Restart the dev script with `--force` (re-runs Vite's dep-optimization step) or wipe the cache and restart fresh:

```bash
# preferred — the project script wraps Vite + the CP dev runner together
npm run dev:with-cp --force

# alternative — explicit cache wipe + plain restart
rm -rf node_modules/.vite && npm run dev:with-cp
```

Confirmed working as the fix during UAMS-Web/uams-statamic-archive#86 verification (2026-05-20).

## When it happens

| Trigger                                                  | Frequency                                  |
| -------------------------------------------------------- | ------------------------------------------ |
| `npm ci` or `npm install` while `vite` is running        | Common (most reports trace back here)      |
| Branch switch that touched `package-lock.json`           | Common                                     |
| `composer update` for a package that ships JS deps       | Occasional                                 |
| Vite version bump                                        | Once per upgrade                           |

## References

- PR UAMS-Web/uams-statamic-archive#725 (UAMS-Web/uams-statamic-archive#86) — diagnosed during the visual-regression verification step on `/spread-kindness`.
- Vite docs on dep optimization: <https://vite.dev/guide/dep-pre-bundling.html>
