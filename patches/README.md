# Statamic Composer Patch Workflow

This directory contains generated Composer patch files and their canonical
snippet sources.

**This file documents each patch individually — what it fixes, why it exists,
and what retires it. The lifecycle those patches move through** — write it,
report it upstream, offer the fix, retire it when a release carries it, and the
four-ticket GitHub chain that tracks the wait — **lives in the
[`upstream-patch-lifecycle`](../.claude/skills/upstream-patch-lifecycle/SKILL.md)
skill.** Read that before carrying a new patch or removing an existing one; the
removal ordering in particular is load-bearing, because updating a package before
its patch comes out hard-fails `composer install` for everyone under
`composer-exit-on-patch-failure: true`.

## Directory layout

- Canonical snippet sources live in `patches/src/`
- Generated patch files live in `patches/`

## Existing patch sets

### Collection `createLabel()`

- Basename: `statamic-collection-create-label-blueprint`
- Related Statamic feature request:
  `https://github.com/statamic/ideas/issues/1448`
- Original snippet:
  `patches/src/statamic-collection-create-label-blueprint.original.php`
- Patched snippet:
  `patches/src/statamic-collection-create-label-blueprint.patched.php`
- Generated patch:
  `patches/statamic-collection-create-label-blueprint.patch`
- Generator command:
  `php artisan app:generate-statamic-collection-create-label-patch`
- Target vendor file:
  `vendor/statamic/cms/src/Entries/Collection.php`

### Taxonomy `createLabel()`

- Basename: `statamic-taxonomy-create-label-blueprint`
- Related Statamic feature request:
  `https://github.com/statamic/ideas/issues/1448`
- Original snippet:
  `patches/src/statamic-taxonomy-create-label-blueprint.original.php`
- Patched snippet:
  `patches/src/statamic-taxonomy-create-label-blueprint.patched.php`
- Generated patch:
  `patches/statamic-taxonomy-create-label-blueprint.patch`
- Generator command:
  `php artisan app:generate-statamic-taxonomy-create-label-patch`
- Target vendor file:
  `vendor/statamic/cms/src/Taxonomies/Taxonomy.php`

### CP updates nav badge (`UpdatesOverview`)

- Basename: `statamic-updates-overview-badge`
- Original snippet:
  `patches/src/statamic-updates-overview-badge.original.php`
- Patched snippet:
  `patches/src/statamic-updates-overview-badge.patched.php`
- Generated patch:
  `patches/statamic-updates-overview-badge.patch`
- Generator command:
  `php artisan app:generate-statamic-updates-overview-badge-patch`
- Target vendor file:
  `vendor/statamic/cms/src/Updater/UpdatesOverview.php`
- Method: `checkForAddonUpdates()`
- Test (asserts patch is applied in vendor):
  `tests/Unit/UpdatesOverviewBadgeTest.php`

**When removing this patch** (after upstream ships an equivalent fix), delete or
revert all of the following together — do not leave the test behind; it only
asserts the patched vendor file contains `availableUpdatesCount() > 0`:

- `patches/statamic-updates-overview-badge.patch`
- `patches/src/statamic-updates-overview-badge.original.php`
- `patches/src/statamic-updates-overview-badge.patched.php`
- `app/Console/Commands/GenerateStatamicUpdatesOverviewBadgePatch.php`
- `tests/Unit/UpdatesOverviewBadgeTest.php`
- The `Align CP updates badge with updater availableUpdatesCount` entry under
  `extra.patches.statamic/cms` in `composer.json`
- The matching block in `patches.lock.json` (or run `composer patches-relock
  --no-interaction` after editing `composer.json`), followed by `composer update
  --lock --no-interaction` — removing an `extra.patches` entry changes Composer's
  content hash just as adding one does

**Problem.** The CP **Updates** nav badge calls `UpdatesOverview`, which counts
addons with `!isLatestVersion()`. That compares the installed version to Outpost
`latest_version` with strict equality. The **Updater** index uses
`changelog()->availableUpdatesCount()` (releases newer than installed). When
Composer installs a version **ahead** of Outpost metadata (e.g. admin-bar
`0.3.2` installed while Outpost still lists `0.3.0`), the badge shows pending
updates but `/cp/updater` shows none.

**Local fix.** Replace `reject->isLatestVersion()` with marketplace addons that
have `availableUpdatesCount() > 0`, matching the updater UI.

**Upstream report (copy into a statamic/cms issue).** Include:

- **Title:** CP Updates nav badge count disagrees with updater index when addon is newer than Outpost `latest_version`
- **Statamic version:** e.g. `6.19.0` (pin whatever you run)
- **Symptom:** Nav badge shows a non-zero count; `/cp/updater` lists all addons as up to date.
- **Root cause:** `UpdatesOverview::checkForAddonUpdates()` uses
  `Addon::all()->reject->isLatestVersion()` (`Addon::isLatestVersion()` uses
  `version_compare($installed, $latestVersion, '=')` against Outpost
  `latest_version`). The updater index uses `availableUpdatesCount()` from the
  releases API. When `installed > latest_version` in Outpost, `isLatestVersion()`
  is false but there are zero upgrade releases.
- **Reproduction:** Install an addon version newer than Outpost `latest_version`
  (example: `el-schneider/statamic-admin-bar` `0.3.2` while Outpost packages API
  returns `latest_version` `0.3.0` and releases top out at `0.3.0`). Clear
  `updates-overview.*` cache keys, load CP — badge counts the addon; updater does
  not.
- **Suggested fix:** In `UpdatesOverview::checkForAddonUpdates()`, count addons
  with `availableUpdatesCount() > 0` (and keep `existsOnMarketplace()`), or
  treat `version_compare($installed, $latestVersion, '>=')` as up to date in
  `isLatestVersion()` if that method should mean “no downgrade required.”
- **Files:** `src/Updater/UpdatesOverview.php`, `src/Addons/Addon.php` (`isLatestVersion()` is only referenced from `UpdatesOverview`).
- **Reference patch:** This repo’s `patches/statamic-updates-overview-badge.patch` (see **When removing this patch** above for the full removal list, including `tests/Unit/UpdatesOverviewBadgeTest.php`).

### AddonChangelog `isLicensed()` (null-safe license)

**Lockfile status:** Removed from `patches.lock.json` for Statamic **6.18.1** and newer: core `AddonChangelog::isLicensed()` already uses `$this->addon->license()?->versionLimit()`, so the Composer patch no longer applies. Regenerate `patches/statamic-addon-changelog-null-license.patch` and re-add it only if you pin an older Statamic where the bug remains.

- Basename: `statamic-addon-changelog-null-license`
- Original snippet:
  `patches/src/statamic-addon-changelog-null-license.original.php`
- Patched snippet:
  `patches/src/statamic-addon-changelog-null-license.patched.php`
- Generated patch:
  `patches/statamic-addon-changelog-null-license.patch`
- Generator command:
  `php artisan app:generate-statamic-addon-changelog-null-license-patch`
- Target vendor file:
  `vendor/statamic/cms/src/Updater/AddonChangelog.php`

### Stache store reads (skip a file that vanishes before its read)

Six patches, one per method, because the generator replaces a single method per patch. The first two are listed here; the table below lists the rest.

- Basenames: `statamic-stache-store-vanished-file-paths` and `statamic-stache-store-vanished-file-modified`
- Original snippets:
  `patches/src/statamic-stache-store-vanished-file-paths.original.php`,
  `patches/src/statamic-stache-store-vanished-file-modified.original.php`
- Patched snippets:
  `patches/src/statamic-stache-store-vanished-file-paths.patched.php`,
  `patches/src/statamic-stache-store-vanished-file-modified.patched.php`
- Generated patches:
  `patches/statamic-stache-store-vanished-file-paths.patch`,
  `patches/statamic-stache-store-vanished-file-modified.patch`
- Generator commands:
  `php artisan app:generate-statamic-stache-store-vanished-file-paths-patch`,
  `php artisan app:generate-statamic-stache-store-vanished-file-modified-patch`
- Target vendor file:
  `vendor/statamic/cms/src/Stache/Stores/Store.php`
- Test (a file deleted before its read is skipped and not written back; a present empty file is still built):
  `tests/Feature/Stache/StoreVanishedFileTest.php`
- Tracking issue: #2643; the upstream report rides with #2241

**Problem.** Both methods read a path the Stache has already stat'ed with `Statamic\Facades\File::get()`,
which returns null for a missing file rather than raising. `makeItemFromFile()` then parses null to an
empty array, generates an id, and for an entry writes the file back. So a file deleted between the stat
and the read, by a parallel worker in the shared content mirror or by a Control Panel delete during an
index build, is recreated as an empty entry under a new id. Probed in an isolated Stache on macOS.

**Local fix.** When the read returns null, the method lists the file's directory again, and skips the file
only when that listing succeeds and no longer contains it. An empty file that is present also reads as
null, and keeps Statamic's behavior. The check is a listing rather than `file_exists()` for the reason the
traverser patch records below.

The same guard covers the other reads of an already-recorded path, one patch per method:

| Patch | Method | On a vanished file |
| --- | --- | --- |
| `statamic-stache-terms-vanished-file-paths` | `TaxonomyTermsStore::paths()` | skipped from the index build |
| `statamic-stache-terms-vanished-file-get-item` | `TaxonomyTermsStore::getItem()` | returns null, not cached |
| `statamic-stache-basic-vanished-file-get-item` | `BasicStore::getItem()` | returns null, not cached. Since `statamic/cms` 6.31.0 (`statamic/cms#15310`) the method itself returns null for a file already missing at its `File::exists()` check; the patch covers a delete landing between that check and the read, which `StoreVanishedFileTest` pins |
| `statamic-stache-terms-vanished-file-modified` | `TaxonomyTermsStore::getItemFromModifiedPath()` | returns no items; it calls the patched parent and then `localizations()`, which would otherwise fatal on the new null |

Each has its snippet pair in `patches/src/<basename>.{original,patched}.php` and its generator
`php artisan app:generate-<basename>-patch`, and targets `vendor/statamic/cms/src/Stache/Stores/`.
The `terms-vanished-file-modified` patch must be removed together with the `store-vanished-file-modified` one.

`CollectionEntriesStore::writeItemToDisk()` needs no patch: its unique-path probe takes the first candidate
that reads as null, and a file deleted before that read is genuinely free. `StoreVanishedFileTest` pins that.

Not covered, and named as residual on #2643: the gap inside `File::get()` itself, where a delete between its
`isFile()` check and its read raises an uncaught `ErrorException`; and `RevisionsStore::makeItemFromFile()`,
which reads the file again.

**Retirement condition.** A `statamic/cms` release in which a Stache store does not rebuild a file deleted
between its stat and its read. Verify by tag on Packagist, not by the Releases feed.

**Removal checklist.**

- The six `.patch` files: `statamic-stache-store-vanished-file-{paths,modified}`, `statamic-stache-terms-vanished-file-{modified,paths,get-item}` and `statamic-stache-basic-vanished-file-get-item`
- Their twelve `patches/src/` snippets
- Their six `app/Console/Commands/Generate*VanishedFile*Patch.php` generators (the traverser's own is listed in its section)
- The six `(temporary; see #2643)` entries under `extra.patches.statamic/cms` in `composer.json`, then relock both lock files
- `tests/Feature/Stache/StoreVanishedFileTest.php`, which asserts the patched behavior and should be kept only if the upstream fix satisfies it
- This section

### Stache `Traverser::traverse()` (skip a file that vanishes before its stat)

- Basename: `statamic-stache-traverser-vanished-file`
- Original snippet:
  `patches/src/statamic-stache-traverser-vanished-file.original.php`
- Patched snippet:
  `patches/src/statamic-stache-traverser-vanished-file.patched.php`
- Generated patch:
  `patches/statamic-stache-traverser-vanished-file.patch`
- Generator command:
  `php artisan app:generate-statamic-stache-traverser-vanished-file-patch`
- Target vendor file:
  `vendor/statamic/cms/src/Stache/Traverser.php`
- Test (a vanished file is skipped; a present file whose stat fails, and a directory that cannot be listed, still raise):
  `tests/Feature/Stache/TraverserVanishedFileTest.php`
- Tracking issue: #2639; the upstream report rides with #2241

**Problem.** `traverse()` lists a store's directory, then calls `getMTime()` on each file. A file
removed between those two steps makes `getMTime()` raise `RuntimeException: SplFileInfo::getMTime():
stat failed`, and the raise aborts the whole index build as a 500 for whichever request triggered it.
Parallel test workers share `storage/framework/testing/content-mirror`, so a suite that creates and
removes entries on one worker can remove a file another worker has just listed. The failure then
lands in an unrelated suite, with a stack trace into `vendor/statamic/cms` and nothing naming the cause.
Observed during #2633's validation as a red in `FrontEndAccountFlowTest`, on a file under
`classifieds/inside_classifieds` that `ClassifiedsPostingTest` creates and deletes. The same window
exists in production, where a Control Panel delete can land during an index build.

**Local fix.** When `getMTime()` raises, the method lists the file's directory again. It skips the
file only when that listing succeeds and no longer contains it, and re-raises otherwise. The check
is a listing rather than `file_exists()` because a stat can fail on a present file: if a directory
loses search permission after it is listed, every stat inside it fails, and `file_exists()` reports `false` too, while
a listing of the directory still includes the file (measured on macOS as a non-root user). A `file_exists()` check would
skip that file silently.

This narrows the race rather than closing it. `Store::paths()` and `getItemFromModifiedPath()`
read each returned path with `File::get()`, which returns null for a file deleted after the stat, and
the two `statamic-stache-store-vanished-file` patches below skip that file. A whole subdirectory
removed before the stat fails the re-listing and is re-raised, and #2643 accepts that as residual.

It was its own patch rather than part of the shared-filter one, which retired with `statamic/cms`
6.35.0 (#2260): the two changed different methods, and each retires on its own upstream fix.

**Retirement condition.** A `statamic/cms` release in which `Traverser::traverse()` tolerates a
file removed between listing and stat. Verify by tag on Packagist, not by the Releases feed.

**Removal checklist.**

- `patches/statamic-stache-traverser-vanished-file.patch`
- `patches/src/statamic-stache-traverser-vanished-file.original.php`
- `patches/src/statamic-stache-traverser-vanished-file.patched.php`
- `app/Console/Commands/GenerateStatamicStacheTraverserVanishedFilePatch.php`
- The `Skip a Stache file removed between listing and stat so a concurrent delete cannot abort the traversal (temporary; see #2241)`
  entry under `extra.patches.statamic/cms` in `composer.json` — leave the package's other
  entries in place — then relock both lock files
- This section

`tests/Feature/Stache/TraverserVanishedFileTest.php` and
`tests/Support/Doubles/StatFailingStreamWrapper.php` **stay**. The test asserts behavior an
upstream fix must also satisfy; it is the proof the removal worked rather than part of the patch.

### `pest-plugin-browser` per-attempt retry timeout

**Not a Statamic patch, and not generated.** Hand-written against a dev
dependency, so it has no `patches/src/` snippet pair and no `app:generate-*`
command. Edit the `.patch` file directly, then relock. The snippet and generator
steps under **Required end-to-end steps** below do not apply to it.

- Basename: `pest-plugin-browser-per-attempt-timeout`
- Patch file: `patches/pest-plugin-browser-per-attempt-timeout.patch`
- Target vendor file: `vendor/pestphp/pest-plugin-browser/src/Execution.php`
- Test (behavioral, drives the patched retry loop):
  `tests/Unit/PestBrowserRetryPatchTest.php`
- Upstream issue, open: `https://github.com/pestphp/pest/issues/1852`
- Original report, closed on 2026-10-20 as a duplicate of #1852:
  `https://github.com/pestphp/pest/issues/1755`
- Downstream symptom: `https://github.com/pestphp/pest/issues/1763`

Touches a **different file** from the sibling `Playwright/Client.php` patch, so
the two never produce conflicting hunks.

**Problem.** `Execution::waitForExpectation()` wraps every webpage method — not
just assertions — in a retry loop, and executes each attempt inside
`Playwright::usingTimeout(1000, $callback)`. `Configuration::timeout()` only
bounds how long the loop keeps retrying; **only the final post-loop call receives
the configured value**. So `Playwright::setTimeout(30_000)` in `tests/Pest.php`
does not do what its comment says: every attempt is still cut off at one second.

Upstream verified two consequences: wall-clock scaled with the configured timeout
rather than the work (a constant 2s delay cost 5.32s at 3000ms and 14.82s at
12000ms), and side-effecting actions were re-executed, because a retry loop was
being applied to operations that are not idempotent.

That matters here because the 30s timeout was added deliberately (#1009): a CP
render under load was flaking even trivial pages at the 5s default. With
per-attempt budgets pinned at 1000ms, that mitigation was not actually in effect.

**Local fix.** Give each attempt the configured timeout. The per-attempt
truncation is the root cause of **both** consequences — an operation that fits
inside the budget now completes on its first attempt and is never retried, so the
re-execution disappears without having to enumerate which methods are actions.
Retry itself is untouched, so an assertion waiting on an async render still
re-checks until the window closes.

**The attempt budget is clamped to what is left of the window, and that clamp is
load-bearing** — without it this patch would *raise* the worst case rather than
hold it. The loop tests its deadline only at the top and nothing else bounds an
attempt, so handing every attempt the full timeout would let one admitted at
`$end - ε` run to **2×** the timeout, and the unconditional post-loop call would
then push the ceiling to **3×**. With the clamp the loop stays inside one timeout
and the final call inside another, which is the same ~2× ceiling as before the
patch, where the 1000ms attempts could only overrun the window by a second. The
first attempt is unaffected — the whole window is still remaining at that point —
so an operation that fits the budget still completes on attempt one, which is the
point of the patch.

A genuinely unsatisfiable assertion therefore still fails in the same bounded time
rather than inheriting timeout × retry-count. `tests/Unit/PestBrowserRetryPatchTest.php`
asserts the clamp directly, because "the bound is unchanged" is the whole safety
argument for this patch and was **wrong** in an earlier draft that omitted it.

**Note on scope.** This is deliberately narrower than upstream's proposed
`pest-plugin-browser` PR #227, which splits retryable assertions from
side-effecting actions wholesale — that PR is based on `4.x` and would mean
enumerating every action method and diverging sharply from the installed `5.x`.
The installed v5.2.0 already keeps `typeSlowly`, `click`, `press`, `keys`, `drag`,
`append` and the navigation methods out of the retry loop, so the specific
upstream reproduction is already mitigated; this patch removes the underlying
truncation that produced it. **Residual:** any other action that genuinely
throws `ExpectationFailedException` is still retried within the window. That is
inherent to the retry design and is what PR #227 exists to resolve upstream.

**When removing this patch:** the retirement condition is the first
`pest-plugin-browser` release that separates action execution from the assertion
retry loop (or otherwise stops pinning attempts to 1000ms). When it ships, delete
or revert all of:

- `patches/pest-plugin-browser-per-attempt-timeout.patch`
- `tests/Unit/PestBrowserRetryPatchTest.php`
- The `staticMethod.internalClass` `ignoreErrors` entry pinned to that test's
  path in `phpstan.neon.dist`
- The matching `pest-plugin-browser` entry under `extra.patches` in
  `composer.json` (leave the package's other entries in place if they remain)
- The matching block in `patches.lock.json` (or run `composer patches-relock
  --no-interaction` after editing `composer.json`), followed by `composer update
  --lock --no-interaction` — removing an `extra.patches` entry changes Composer's
  content hash just as adding one does

### `pest-plugin-browser` shell-free Playwright server start

**Not a Statamic patch, and not generated.** Hand-written against a dev
dependency, so it has no `patches/src/` snippet pair and no `app:generate-*`
command. Edit the `.patch` file directly, then relock. The snippet and generator
steps under **Required end-to-end steps** below do not apply to it.

- Basename: `pest-plugin-browser-server-no-shell`
- Patch file: `patches/pest-plugin-browser-server-no-shell.patch`
- Target vendor file:
  `vendor/pestphp/pest-plugin-browser/src/Playwright/Servers/PlaywrightNpmServer.php`
- Test (asserts patch is applied in vendor):
  `tests/Feature/PestBrowserServerPatchTest.php`
- Upstream issue, open, no PR:
  `https://github.com/pestphp/pest/issues/1754`

Touches a **third** file, distinct from the `Playwright/Client.php` and
`Execution.php` patches, so none of the three can produce conflicting hunks.

**Problem.** The server is started with `Process::fromShellCommandline()`, and
Symfony only prepends `exec` for **array**-form commands. On Debian/Ubuntu, where
`/bin/sh` is dash, that yields:

```
php (pest) -> sh -c "playwright run-server …" -> node server
```

`stop()` signals the `sh`, which exits, and the `node` child is reparented to PID
1 and survives. When output is piped the orphan holds stdout open and the reader
waits for an EOF that never comes, so the run never returns. The `browser` CI job
is `runs-on: ubuntu-latest` and a GitHub Actions runner always captures step
output through a pipe — exactly the failing configuration — and that job is last
in the cost-ordered chain, so a hang there has already paid for every preceding
job. It works silently on macOS, where bash `exec`-replaces itself for a single
`-c` command, so a developer there cannot reproduce what CI hits.

An orphaned server is also one of the ways the stale
`.temp/playwright-server.json` documented in `tests/Browser/README.md` gets
written, so this removes a cause of that gotcha rather than a symptom.

**Local fix.** Build the process from **argv** instead of a shell string — the
report's preferred option, which removes the shell rather than papering over it.
On Unix, `proc_open` receives the array and execs the binary directly, so the
server *is* the child and the existing signal handling reaches it. On Windows,
Symfony rebuilds an escaped command string exactly as before, so it is a no-op
there. The formatted command is split on whitespace, which is safe for this
command: a relative path with no spaces, and flag values that are each a single
token.

The report's third suggestion — escalating the `SIGTERM` fallback to `SIGKILL` —
is **already** in the installed v5.0.0, which calls `stop(timeout: 0.1)` with a
`null` signal; Symfony then sends `SIGTERM`, waits, and follows with `SIGKILL`.
(The v4 line passed `signal: SIGTERM` explicitly, which suppressed that
escalation.) So only the shell removal is needed here.

**Verification gap — read this before trusting the patch on Linux.** The failure
this fixes is Linux-only, and it could not be exercised here: the dev environment
is Windows, and remote CI is disabled org-wide, so neither the `pgrep -f
'playwright run-server'` check, the piped-output check, nor a `browser` job run on
`ubuntu-latest` has been performed. What **was** verified is that on Windows the
patched start path still launches the server, the suite still passes, and no
`node` process survives the run. The Linux behavior rests on the Symfony
`exec`/argv semantics read directly out of `Process::start()`, not on a
measurement. Confirm it on the first Linux `browser` run.

**When removing this patch:** the retirement condition is the first
`pest-plugin-browser` release that no longer orphans the server process. When it
ships, delete or revert all of:

- `patches/pest-plugin-browser-server-no-shell.patch`
- `tests/Feature/PestBrowserServerPatchTest.php` — do not leave it behind; it
  asserts patched vendor source and goes red the moment the patch retires
- The matching `pest-plugin-browser` entry under `extra.patches` in
  `composer.json` (leave the package's other entries in place if they remain)
- The matching block in `patches.lock.json` (or run `composer patches-relock
  --no-interaction` after editing `composer.json`), followed by `composer update
  --lock --no-interaction` — removing an `extra.patches` entry changes Composer's
  content hash just as adding one does

### `pest-plugin-browser` navigation retry on `net::ERR_NO_BUFFER_SPACE`

**Not a Statamic patch, and not generated.** Hand-written against a dev
dependency, so it has no `patches/src/` snippet pair and no `app:generate-*`
command. Edit the `.patch` file directly, then relock. The snippet and generator
steps under **Required end-to-end steps** below do not apply to it.

- Basename: `pest-plugin-browser-navigation-no-buffer-space-retry`
- Patch file: `patches/pest-plugin-browser-navigation-no-buffer-space-retry.patch`
- Target vendor files: `vendor/pestphp/pest-plugin-browser/src/Playwright/Page.php`
  (changed) and `vendor/pestphp/pest-plugin-browser/src/Playwright/NavigationRetry.php`
  (added)
- Test (behavioral, drives the retry with recording callables, and checks
  `Page::goto()` calls it): `tests/Unit/PestBrowserNavigationRetryPatchTest.php`
- Ticket: #585
- Upstream issue: none filed

Touches a file none of the other `pest-plugin-browser` patches touch, so the
three cannot produce conflicting hunks.

**Problem.** On a hosted Windows runner, `KbArticleRatingActiveStateTest` failed
once (CI run `41964133422`, attempt 3) with `net::ERR_NO_BUFFER_SPACE` while
navigating to a static fixture on `127.0.0.1`; the same head passed the job on
attempt 1. Chromium reports that error when Windows refuses a socket with its
"no buffer space available" error. It is not a connection leak in the suite: sampled on Windows 11
every few seconds through a Control Panel browser test, which is the heaviest kind
in the suite, loopback connections in `TIME_WAIT` stayed between 5 and 19,
against a dynamic port range of 16,384. So the condition belongs to the runner,
and the plugin's `Page::goto()` turned it into a failed test on its first
occurrence.

**Local fix.** `Page::goto()` runs its navigation through
`NavigationRetry::onNoBufferSpace()`, which retries a navigation that failed with
an `ExpectationFailedException` naming `net::ERR_NO_BUFFER_SPACE` up to twice,
waiting 0.5s and then 1s. Any other navigation error, and any other exception
type, is thrown on its first occurrence, and a third refusal is thrown as it
came. Each retry writes one line to STDERR, starting
`pest-plugin-browser: retry`, so a job log shows when the retry fired. Every
navigation goes through `goto()`, both `visit()` and a page's `navigate()`, which
is why the retry sits in the vendor class rather than in a test helper; the
plugin's classes are `final`, so there is no subclass to put it in.

**When removing this patch:** retire it when `pest-plugin-browser` retries this
error itself, or when the Windows `browser` job logs have shown no
`pest-plugin-browser: retry` line for long enough to decide the runner no longer
produces it. Then delete or revert all of:

- `patches/pest-plugin-browser-navigation-no-buffer-space-retry.patch`
- `tests/Unit/PestBrowserNavigationRetryPatchTest.php`, which goes red the
  moment the patch retires, because the class it drives no longer exists
- The matching `pest-plugin-browser` entry under `extra.patches` in
  `composer.json` (leave the package's other entries in place)
- The matching block in `patches.lock.json` (or run `composer patches-relock
  --no-interaction` after editing `composer.json`), followed by `composer update
  --lock --no-interaction`

### `pest-plugin-mutate` (moved to `uams-web/patches-pest-plugin-mutate`)

The seven `pest-plugin-mutate` patches are no longer carried here. They ship in [`uams-web/patches-pest-plugin-mutate`](https://github.com/UAMS-Web/patches-pest-plugin-mutate), which this repository requires in `require`; the package's own `extra.patches` applies them, and `patches.lock.json` records each with `"provenance": "dependency:pestphp/pest-plugin-mutate"` (#635). The package's [`README.md`](https://github.com/UAMS-Web/patches-pest-plugin-mutate/blob/main/README.md) lists the set and its application order; a change to any patch, or a retirement, is a release of that package, never an edit here.

The guard tests stay in this repository and read the vendored source, so each goes red if the package stops applying its patch:

| Patch | Tracked by | Guard test |
| --- | --- | --- |
| `pest-plugin-mutate-mutant-process-launch.patch` | `UAMS-Web/uams-statamic-archive#2322` | `tests/Feature/Config/MutantProcessLaunchPatchTest.php` |
| `pest-plugin-mutate-annotation-parsing.patch` | `UAMS-Web/uams-statamic-archive#2327` | `tests/Feature/Config/MutateIgnorePatchTest.php` |
| `pest-plugin-mutate-windows-mutant-child.patch` | `UAMS-Web/uams-statamic-archive#2524` | `tests/Feature/Config/MutantWindowsChildPatchTest.php` |
| `pest-plugin-mutate-drop-run-filter.patch` | `UAMS-Web/uams-statamic-archive#2570` | `tests/Feature/Config/MutantRunFilterPatchTest.php` |
| `pest-plugin-mutate-windows-filter-length.patch` | `UAMS-Web/uams-statamic-archive#2578`, `UAMS-Web/uams-statamic-archive#3114` | `tests/Feature/Config/MutantFilterLengthPatchTest.php` |
| `pest-plugin-mutate-write-mutated-source.patch` | `UAMS-Web/uams-statamic-archive#2812` | `tests/Feature/Config/MutantModifiedSourceWritePatchTest.php` |
| `pest-plugin-mutate-drop-parallel-runner-options.patch` | `UAMS-Web/uams-statamic-archive#2926`, `UAMS-Web/uams-statamic-archive#2947` | `tests/Feature/Config/MutantParallelRunnerOptionsPatchTest.php` |

The per-patch write-ups this file carried (what each fixes, the measurements, the retirement conditions) are in this file's history at the commit before #635, `git show 4551ce289:patches/README.md`.

## Required end-to-end steps

1. Update the relevant `.original.php` and `.patched.php` snippet files in
   `patches/src/`.
2. Generate the patch file(s) (**requires `git` on `PATH`**; uses `git diff`
   so output is compatible with `cweagans/composer-patches` / `git apply`):
   - `php artisan app:generate-statamic-collection-create-label-patch`
   - `php artisan app:generate-statamic-taxonomy-create-label-patch`
   - `php artisan app:generate-statamic-updates-overview-badge-patch`
   - `php artisan app:generate-statamic-addon-changelog-null-license-patch`
   - `php artisan app:generate-statamic-stache-traverser-vanished-file-patch`
   - `php artisan app:generate-statamic-stache-store-vanished-file-paths-patch`
   - `php artisan app:generate-statamic-stache-store-vanished-file-modified-patch`
   - `php artisan app:generate-statamic-stache-terms-vanished-file-modified-patch`
   - `php artisan app:generate-statamic-stache-terms-vanished-file-paths-patch`
   - `php artisan app:generate-statamic-stache-terms-vanished-file-get-item-patch`
   - `php artisan app:generate-statamic-stache-basic-vanished-file-get-item-patch`
3. Register the new command in the step-2 list above, so the regenerate-all pass
   keeps covering every patch. (Missed once, on #1673 — a generator that is not in
   this list is one nobody reruns after a Statamic bump.)
4. Ensure `composer.json` has patch entries in `extra.patches.statamic/cms`
   that point to the generated patch file path(s).
5. Rebuild patch lock metadata:
   - `composer patches-relock --no-interaction`
6. Refresh Composer's own content hash — **this is a second, different lock file**:
   - `composer update --lock --no-interaction`
   - `composer patches-relock` rewrites **`patches.lock.json`** (the patch
     SHA-256s). It does **not** touch **`composer.lock`**. Composer folds the
     whole `extra` block into `composer.lock`'s `content-hash`, so adding or
     editing an `extra.patches` entry leaves that hash stale, and every
     subsequent `composer install` warns "The lock file is not up to date with
     the latest changes in composer.json". `composer update --lock` recomputes
     the hash and moves no dependency.
   - The same applies to any other `composer.json` key that feeds the hash
     (`name`, `require*`, `autoload*`, `config`, `extra`) — not just patches.
7. Verify the manifest and lock agree:
   - `composer validate --no-check-publish --no-check-all`
   - Expect no `# Lock file errors` section. ("No license specified" is a
     pre-existing general warning and is expected.)
8. Apply or verify patch application:
   - `composer install`
   - or `composer update statamic/cms cweagans/composer-patches`
9. Run formatter before commit:
   - `vendor/bin/pint --dirty --format agent`

## Adding a new Statamic patch set

1. Create two snippet files in `patches/src/` using a shared basename:
   - `<basename>.original.php`
   - `<basename>.patched.php`
2. Add a new Artisan command in `app/Console/Commands/` that delegates to
   `App\Services\GenerateStatamicPatch\StatamicPatchGenerator` and sets:
   - `PATCH_BASENAME`
   - `PATCH_TARGET_DIFF_PATH` (path inside `vendor/statamic/cms/src/...`)
3. Run the command to generate `patches/<basename>.patch`.
4. Register the patch path in `composer.json` under
   `extra.patches.statamic/cms`.
5. Run `composer patches-relock --no-interaction`.
6. Run `composer update --lock --no-interaction` — step 5 rewrites
   `patches.lock.json`, **not** `composer.lock`. See step 5 of *Required
   end-to-end steps* for why editing `extra.patches` leaves Composer's own
   content hash stale.
7. Confirm with `composer validate --no-check-publish --no-check-all` that no
   `# Lock file errors` section is reported.

## Notes

- Keep snippet files as full PHP classes so linters and tooling can parse them.
- **`.original.php`** must match **unpatched** upstream for the installed
  Statamic version. If Composer patches are already applied, the vendor method
  matches **`.patched.php`** instead; the generator reconstructs the unpatched
  baseline from your **`.original.php`** snippet before running `git diff`.
- Do not edit generated `.patch` files by hand. Regenerate with the Artisan
  commands above (they run **`git diff`** in a temp repo).
- `cweagans/composer-patches` v2 applies patches with **`git apply`**, not GNU
  `patch`. After any `.patch` change, run **`composer patches-relock`** so
  `patches.lock.json` SHA-256 values stay in sync.
