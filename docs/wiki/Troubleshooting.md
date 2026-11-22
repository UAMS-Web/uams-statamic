One entry per symptom, as you meet it.

- [A public form renders without fields, and DevTools shows `504 Outdated Optimize Dep`](#a-public-form-renders-without-fields-and-devtools-shows-504-outdated-optimize-dep)
- [A published news release has no PDF](#a-published-news-release-has-no-pdf)
- [Tests report as skipped in a fresh clone](#tests-report-as-skipped-in-a-fresh-clone)

## A public form renders without fields, and DevTools shows `504 Outdated Optimize Dep`

**Cause:** Vite's cache of pre-bundled dependencies went stale while the dev server was running, so Alpine never starts. It is common after `npm ci`, after switching to a branch that changes `package-lock.json`, and after a dependency upgrade.

**Fix:** follow [`docs/runbooks/vite-stale-optimize-dep-cache.md`](https://github.com/UAMS-Web/uams-statamic/blob/main/docs/runbooks/vite-stale-optimize-dep-cache.md), which gives the symptoms, the cause and the fix in full.

## A published news release has no PDF

**Cause:** news release PDFs are rendered with a headless Chrome that `npm` does not download, because `.npmrc` sets `ignore-scripts=true`. Without it the entry saves with no PDF, and the reason is only in `storage/logs/laravel.log`.

**Fix:** run `npx --no-install puppeteer browsers install chrome-headless-shell`, then save the published entry again: saving a published `news_release` regenerates its PDF. In a development copy, `node scripts/worktree-slots.mjs status` names the browser when it is missing.

## Tests report as skipped in a fresh clone

**Cause:** the clone has no access to the private `content/` or `resources/forms/` submodules, and the tests that need them skip rather than fail.

**Fix:** none is needed when the clone is not meant to have content. Otherwise fetch the submodules; see [Git submodules](Git-submodules.md#a-clone-without-access-to-the-submodules).
