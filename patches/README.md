# Composer vendor patches

This repository carries no patch files of its own. Every vendor patch it applies ships in a `uams-web/patches-*` package, read through a `vcs` entry in `composer.json`, and `patches.lock.json` records each patch with its path under `vendor/uams-web/` and its `sha256` (#642, #638). A change to a patch, or its retirement, is a release of the package that ships it, never an edit here.

The lifecycle those patches move through (write it, report it upstream, offer the fix, retire it when a release carries it, and the ticket chain that tracks the wait) lives in the [`upstream-patch-lifecycle`](../.claude/skills/upstream-patch-lifecycle/SKILL.md) skill. Read it before adopting a package release or retiring a patch: the removal order is load-bearing, because updating a package before its patch comes out fails `composer install` for everyone under `composer-exit-on-patch-failure: true`.

## Where each set comes from

| Upstream package | Patch package | Required in | Registered by |
| --- | --- | --- | --- |
| `statamic/cms` | [`uams-web/patches-statamic-cms`](https://github.com/UAMS-Web/patches-statamic-cms) | `require` | the package's own `extra.patches` |
| `pestphp/pest-plugin-browser` | [`uams-web/patches-pest-plugin-browser`](https://github.com/UAMS-Web/patches-pest-plugin-browser) | `require-dev` | root `extra.patches`, by `vendor/uams-web/patches-pest-plugin-browser/patches/` paths |
| `pestphp/pest-plugin-mutate` | [`uams-web/patches-pest-plugin-mutate`](https://github.com/UAMS-Web/patches-pest-plugin-mutate) | `require` | the package's own `extra.patches` |

`cweagans/composer-patches` reads a package's own `extra.patches` only from the production `packages` section of `composer.lock`. The browser package is a test dependency, so it is required in `require-dev` and its patches are listed in the root `composer.json` instead; requiring it in `require` would have moved `pestphp/pest`, `phpunit/phpunit` and 46 other test packages into every production install (#642).

**Run `composer patches-relock` and `composer patches-repatch` on a dev install.** On a tree installed with `--no-dev` the browser package is absent, so a relock cannot read its three patch paths: it exits 100 and deletes `patches.lock.json` (restore it with `git checkout -- patches.lock.json`). An install from the committed lock, which is what a deploy runs, applies the `statamic/cms` and `pest-plugin-mutate` patches with or without `--no-dev`.

Each package's `README.md` lists its patches in application order and names the ticket that tracks each upstream fix.

## Guard tests

The guard tests stay in this repository and read the vendored source, so each goes red if a package stops applying its patch. A patch with no row here has no guard test.

| Patch | Tracked by | Guard test |
| --- | --- | --- |
| `statamic-updates-overview-badge.patch` | none | `tests/Feature/UpdatesOverviewBadgeTest.php` |
| `pest-plugin-browser-per-attempt-timeout.patch` | `UAMS-Web/uams-statamic-archive#1816`, `pestphp/pest#1852` | `tests/Unit/PestBrowserRetryPatchTest.php` |
| `pest-plugin-browser-server-no-shell.patch` | `UAMS-Web/uams-statamic-archive#1818`, `pestphp/pest#1754` | `tests/Feature/PestBrowserServerPatchTest.php` |
| `pest-plugin-browser-navigation-no-buffer-space-retry.patch` | #585 | `tests/Unit/PestBrowserNavigationRetryPatchTest.php` |
| `pest-plugin-mutate-mutant-process-launch.patch` | `UAMS-Web/uams-statamic-archive#2322` | `tests/Feature/Config/MutantProcessLaunchPatchTest.php` |
| `pest-plugin-mutate-annotation-parsing.patch` | `UAMS-Web/uams-statamic-archive#2327` | `tests/Feature/Config/MutateIgnorePatchTest.php` |
| `pest-plugin-mutate-windows-mutant-child.patch` | `UAMS-Web/uams-statamic-archive#2524` | `tests/Feature/Config/MutantWindowsChildPatchTest.php` |
| `pest-plugin-mutate-drop-run-filter.patch` | `UAMS-Web/uams-statamic-archive#2570` | `tests/Feature/Config/MutantRunFilterPatchTest.php` |
| `pest-plugin-mutate-windows-filter-length.patch` | `UAMS-Web/uams-statamic-archive#2578`, `UAMS-Web/uams-statamic-archive#3114` | `tests/Feature/Config/MutantFilterLengthPatchTest.php` |
| `pest-plugin-mutate-write-mutated-source.patch` | `UAMS-Web/uams-statamic-archive#2812` | `tests/Feature/Config/MutantModifiedSourceWritePatchTest.php` |
| `pest-plugin-mutate-drop-parallel-runner-options.patch` | `UAMS-Web/uams-statamic-archive#2926`, `UAMS-Web/uams-statamic-archive#2947` | `tests/Feature/Config/MutantParallelRunnerOptionsPatchTest.php` |

## History

The per-patch write-ups this file carried (what each patch fixes, the measurements behind it, and its retirement condition) are in this file's history. For the `statamic/cms` and `pest-plugin-browser` patches, and the snippet sources and `app:generate-statamic-*-patch` commands that generated the Statamic ones, read the commit before #642: `git show 38c79391f:patches/README.md`. For the `pest-plugin-mutate` patches, read the commit before #638: `git show 4551ce289:patches/README.md`. The generators were removed in #642 because their output path no longer exists here and the package repository does not carry them.
