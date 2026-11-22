One entry per symptom, as you meet it.

- [A public form renders without fields, and DevTools shows `504 Outdated Optimize Dep`](#a-public-form-renders-without-fields-and-devtools-shows-504-outdated-optimize-dep)
- [A published news release has no PDF](#a-published-news-release-has-no-pdf)
- [Tests report as skipped in a fresh clone](#tests-report-as-skipped-in-a-fresh-clone)

## A public form renders without fields, and DevTools shows `504 Outdated Optimize Dep`

**Cause:** Vite's cache of pre-bundled dependencies went stale while the dev server was running, so Alpine never starts. It is common after `npm ci`, after switching to a branch that changes `package-lock.json`, and after a dependency upgrade.

**Fix:** follow [`docs/runbooks/vite-stale-optimize-dep-cache.md`](https://github.com/UAMS-Web/uams-statamic/blob/main/docs/runbooks/vite-stale-optimize-dep-cache.md), which gives the symptoms, the cause and the fix in full.

## A published news release has no PDF

**Cause:** the headless Chrome that renders news release PDFs is not installed; the reason is logged only in `storage/logs/laravel.log`.

**Fix:** install it as step 10 of the [installation instructions (README)](https://github.com/UAMS-Web/uams-statamic#installation-instructions) describes, then save the published entry again: saving a published `news_release` regenerates its PDF.

## Tests report as skipped in a fresh clone

**Cause:** the `content/` or `resources/forms/` submodule is empty, and the tests that need it skip rather than fail. Either the clone was made without `--recurse-submodules`, or the account that made it has no access to the private submodule repositories.

**Fix:** none is needed when the clone is not meant to have content. Otherwise, get access to the submodule repositories if the account lacks it, then run `git submodule update --init --recursive`; see [Git submodules](Git-submodules.md#a-clone-without-access-to-the-submodules).
