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

### `pest-plugin-browser` bounded Playwright requests

**Not a Statamic patch, and not generated.** Hand-written against a dev
dependency, so it has no `patches/src/` snippet pair and no `app:generate-*`
command. Edit the `.patch` file directly, then relock. The snippet and generator
steps under **Required end-to-end steps** below do not apply to it.

- Basename: `pest-plugin-browser-client-bounded-requests`
- Patch file: `patches/pest-plugin-browser-client-bounded-requests.patch`
- Target vendor file:
  `vendor/pestphp/pest-plugin-browser/src/Playwright/Client.php`
- Test (behavioral, drives the patched client):
  `tests/Unit/PestBrowserClientPatchTest.php`
- Upstream issues, both open: `https://github.com/pestphp/pest/issues/1801`,
  `https://github.com/pestphp/pest/issues/1781`
- Upstream fix, open against `5.x`:
  `https://github.com/pestphp/pest-plugin-browser/pull/241`

**Problem.** Two defects in `Playwright/Client.php` both end the same way — a
wedged process with no failing assertion and no output.

- `fetch()` coerces a closed connection's `null` return into an empty string.
  `execute()` then loops on it forever, because an empty response has neither an
  `error` nor an `id` key to trigger error handling or a loop exit. Any dropped
  connection burns a core until something else kills the job.
- A `timeout` is sent with an action command but never enforced, so the PHP
  process idles in `stream_select` indefinitely. Assertions are safe (they carry
  `Execution::waitForExpectation`'s own bound); **actions** are not, so one
  mistyped selector hangs the suite with nothing pointing at it.

**Local fix — a PARTIAL port of upstream PR #241.** Two of its three changes are
taken:

- Treat socket closure as an **error** instead of coercing it to `''`. This is
  the busy-loop fix, and the reason the patch exists.
- Send the request timeout as protocol-level **metadata** as well as in `params`.
  Metadata timeouts arrive in Playwright **1.62.0**, where an absent value means
  *no timeout at all*; `package-lock.json` pins **1.61.1**, so this half is inert
  today and becomes live on the next bump.

**The third change is deliberately NOT ported — do not "restore" it.** PR #241
also adds a per-request `TimeoutCancellation` and a wall-clock deadline (base
timeout + 30s grace). That is incompatible with how this repo runs browser tests:
per `tests/Support/CpSmoke.php`, the browser, the in-process Laravel HTTP server,
and the test all share **one Amp event loop**, so a burst of server-side work
starves response handling and a wall-clock deadline fires with nothing actually
wrong.

Measured here, same worktree, same commit, only the patch differing:

| `CpGpsFieldtypeSmokeTest` | Result |
| --- | --- |
| Without the deadline | **passes**, 102.6s |
| With the deadline | **fails**, 144.3s — `did not answer [evaluateExpression] within 60.0 seconds` |

The deadline also bought nothing observable on the pinned Playwright: a zero-match
locator action already fails bounded on 1.61.1, measured at **65.8s unpatched vs
63.3s patched**, because Playwright enforces its own timeout. So the half that
closes `pestphp/pest#1781` upstream is omitted, and that issue stays open for this
repo — accepted, because the observed cost is a red browser suite and the observed
benefit is zero. The omission is pinned by a test so it cannot drift back in.

Only source hunks are ported in any case: the dist archive of
`pest-plugin-browser` ships no `tests/` directory, so upstream's accompanying unit
tests cannot be patched in. `tests/Unit/PestBrowserClientPatchTest.php`
reimplements the relevant ones against this repo's suite instead.

**When removing this patch:** the retirement condition is the first
`pest-plugin-browser` release containing PR #241 or an equivalent fix. **Re-run
the browser suite when it lands** — if that release also carries the wall-clock
deadline this patch omits, expect the CP smokes to start failing for the reason
above, and raise it upstream rather than working around it locally. When it ships,
delete or revert all of:

- `patches/pest-plugin-browser-client-bounded-requests.patch`
- `tests/Unit/PestBrowserClientPatchTest.php`
- The `new.internalClass` and `classConstant.internalClass` `ignoreErrors`
  entries pinned to that test's path in `phpstan.neon.dist`
- The `pest-plugin-browser` entry for this patch under `extra.patches` in
  `composer.json` (leave the package's other entries in place if they remain)
- The matching block in `patches.lock.json` (or run `composer patches-relock
  --no-interaction` after editing `composer.json`), followed by `composer update
  --lock --no-interaction` — removing an `extra.patches` entry changes Composer's
  content hash just as adding one does

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
The installed v5.0.0 already excludes `typeSlowly` from the retry loop, so the
specific upstream reproduction is already mitigated; this patch removes the
underlying truncation that produced it. **Residual:** an action that genuinely
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

### `pest-plugin-mutate` `@pest-mutate-ignore` parsing (three silent defects)

`patches/pest-plugin-mutate-annotation-parsing.patch` — temporary, tracked by #2327 and removed by #2330.

**What it fixes.** Three defects in how `@pest-mutate-ignore` is parsed, all of which fail silently. Two make an annotation suppress *nothing* while reading as discharged in review; the third makes a bare annotation suppress the *wrong set*.

| # | File | Defect |
| --- | --- | --- |
| 1 | `NodeVisitor.php` | the captured tail is a mutator list, so prose after the name matches nothing and the annotation is inert |
| 2 | `NodeVisitor.php` | the empty test runs before any trim, so a trailing space or a docblock's own ` */` inverts the bare form into a no-op |
| 3 | `MutationGenerator.php` | `$mutatorsToIgnore` is never reset per iteration, so a bare marker inherits the previous annotation's list and `?? ['all']` cannot fire |

**Why it matters here.** The mutation criterion in [`adversarial-review`](../.claude/rules/adversarial-review.md) is *no unexplained survivors*, and an equivalent mutant is accounted for by annotating it. Defect 1 leaves the mutant alive, which reads as a wrong reason rather than an annotation that never applied. Defect 3 is worse in the other direction: it silences mutants the suite *kills*, removing the signal that would show if that coverage broke.

**What the patch does.** Resets the accumulator per line, and trims the captured tail before testing it for empty. Both are defect fixes with no semantic extension — whitespace ceasing to change meaning is not behavior anything relies on.

**Deliberately still broken.** `// @pest-mutate-ignore: Name because reason` remains inert, and it is the form people write. Fixing it means extracting the leading identifier of each comma-separated part; measured, that turns the case scoped and correctly leaves an unknown name inert. It is a semantic extension rather than a defect fix, so it is raised as a question for the maintainer in #2328 rather than carried here.

**Measured effect**, on a fixture carrying one annotation form per method, `--covered-only`, both arms with a cold mutation cache:

```
unpatched   8 Mutations for 1 Files created
patched     5 Mutations for 1 Files created
```

**Two traps when verifying this patch.** The plugin caches mutation sets keyed on the file *contents* under `vendor/pestphp/pest-plugin-mutate/.temp/pest-mutate-cache`, so patching the plugin and re-running against an unchanged fixture replays the pre-patch result and reads exactly like "the patch does nothing" — clear that directory between arms. And `composer install` does not re-patch an already-installed package: remove `vendor/pestphp/pest-plugin-mutate` first, or the install reports success while the vendored code stays unpatched.

**Removal checklist** — see #2330. The patch comes out before the package moves, or `composer install` hard-fails under `composer-exit-on-patch-failure: true`.

### `pest-plugin-mutate` Windows mutant child (two false-kill defects)

`patches/pest-plugin-mutate-windows-mutant-child.patch` is temporary, tracked by #2524.

**What it fixes.** On Windows, a mutant child that ran no test, or could write no file, was scored killed. There are two defects, and either one alone produces this. Both are measured on Windows 11 Pro 10.0.26200 / PHP 8.4.20 / v5.0.2:

| File | Defect | What the child did |
| --- | --- | --- |
| `MutationTest.php` | `'--filter="'.implode('|', $filters).'"'`: literal quotes that `cmd.exe` does not survive through the `vendor\bin\pest` batch launcher | exited 255 with `'<Test>::' is not recognized as an internal or external command` whenever two or more tests covered the mutant |
| `Support/StreamWrapper.php` | `stream_lock()` returned `flock($this->resource, LOCK_SH)` for every request | failed its first `file_put_contents(..., LOCK_EX)` with `Permission denied`, because a Windows shared lock denies writes through its own handle, and exited 2. This does not depend on how many tests cover the mutant, only on whether the child writes a locked file. |

**What the patch does.** It passes the filter unquoted, so the `Process` quotes it. It passes the requested lock operation through, and answers PHP's `0` "is locking supported" probe with `true`, because `flock()` rejects `0`.

**It changes macOS and Linux too, in two small ways.**
- **Test selection is now case-insensitive.** There the old quotes reached PHPUnit, whose `CompiledNameFilter` read a value starting with `"` as a `"`-delimited regex and matched it case-sensitively. The unquoted value starts with the class name, so it is parsed as filter syntax and compiled as `{…}i`. The selection is a case-insensitive superset, and is almost always identical.
- **An exclusive lock now excludes.** Before, every `LOCK_EX` became `LOCK_SH`, which silently dropped mutual exclusion rather than failing.

The patched plugin has not been re-measured on macOS.

**Measured effect.** In Pest's report, `untested` means a mutant no test killed, that is, a survivor.
- `App\Jobs\UnpublishExpiredClassifieds` (a `serial` file) went from `11 tested, 100.00%` to `4 untested, 1 timeout, 6 tested`.
- `App\Modifiers\SanitizeUrl` (not `serial`) went from `34 tested, 100.00%` to `3 untested, 31 tested`. Those three are the equivalents its test file already records.

**Upstream.** Both defects are present on `pestphp/pest-plugin-mutate`'s default branch as of 2026-09-25. No upstream issue has been filed, which was an operator decision on #2524, so there is no retirement ticket yet.

**Guard test.** `tests/Feature/Config/MutantWindowsChildPatchTest.php`. The lock assertions exercise the wrapper itself, and its exclusive-lock conflict is refused on every platform, so the guard goes red on macOS and Linux too when the patch is absent.

### `pest-plugin-mutate` run filter (a filtered run scores every mutant killed)

`patches/pest-plugin-mutate-drop-run-filter.patch` is temporary, tracked by #2570.

**What it fixes.** `MutationTest::start()` builds each mutant child from the run's own arguments. When the run was given `--filter`, that filter stays in the child's arguments, and the plugin then appends a second `--filter` naming the covering tests. PHPUnit prints `Option --filter cannot be used more than once` and exits 1 even when every test passes. `hasFinished()` reads any non-zero exit as a kill, so every mutant of a filtered run is scored killed. It affects serial and `--parallel` runs alike, on every platform.

Measured on macOS / PHP 8.4 / v5.0.2 against `App\Widgets\IncompletePublishedEntries`, `DecrementInteger` mutator only, `--parallel`, `--no-cache`:

| Test selection | Unpatched | Patched |
| --- | --- | --- |
| `tests/Feature --filter='IncompletePublishedEntries'` | `15 tested, 100.00%`: 11 children had a failing test, and 4 exited 1 with every test passing | `4 untested, 11 tested` |
| `tests/Feature/IncompletePublishedEntriesOffRequestTest.php`, no `--filter` | `4 untested, 11 tested` | — |

The four survivors include the equivalent `per_page` fallback mutant (`0` to `-1`, which the next line clamps to 25), so they are the control: no test can kill them.

**What the patch does.** It removes `--filter=<pattern>` and `--filter <pattern>` from the arguments each child inherits. The child's own `--filter` is unchanged. The covering tests were collected from the filtered run, so they already sit inside the run's selection, and dropping the run's filter selects nothing extra.

**Upstream.** Present on `pestphp/pest-plugin-mutate`'s default branch as of 2026-09-26. No upstream issue has been filed; that is an action on a third-party tracker, left to the operator, so there is no retirement ticket yet.

**Guard test.** `tests/Feature/Config/MutantRunFilterPatchTest.php`, which reads the vendored source.

### `pest-plugin-mutate` filter length (a heavily covered mutant is scored killed)

`patches/pest-plugin-mutate-windows-filter-length.patch` is temporary, tracked by #2578 and #3114. The file name predates the PCRE half (#3114), which applies on every platform.

**What it fixes.** On Windows, Symfony Process runs every child as `cmd.exe /V:ON /E:ON /D /C (...)`, whatever the executable, and `cmd.exe` accepts at most 8,191 characters. A mutant child's `--filter` names every test that covers the mutant, so a heavily covered mutant can push the command line past that. The child then exits 1 without running a test, and `hasFinished()` scores any non-zero exit as a kill. It is Windows-only.

Measured in `UAMS-Web/uamswp-migration-api-archive#296`, not here: a plugin-shaped child with an 8,008-character command line exited 0 and ran 15 tests, and one with 8,400 characters exited 1 with no output. In that repository's `Auth.php` run, the children scored killed with command lines of 19,104 to 20,657 characters all exited 1 and printed nothing.

**And on every platform, a filter PCRE cannot compile** (#3114). PHPUnit's `CompiledNameFilter` wraps the filter as `{<filter>}i` and `NameFilterIterator` matches with `@preg_match`, so a joined pattern past PCRE's compiled-size limit matches no test name: the child prints `No tests found.` and exits 1, which is scored a kill. Measured on macOS (PHP 8.4.23, PCRE 10.44, `main` at `da824dad8`) with coverage of `tests/Feature/Support` alone: 1,603 covered lines in 93 `app/` files have a filter that does not compile, the largest `AugmentedAssetHook.php` line 40 with 398 patterns and 39,918 characters. The `AugmentedAssetHook.php` run scored `8 tested`, `100.00%` in a 6.59s mutation phase, and a hand child given a byte-identical copy of the source with line 40's filter exited 1. The same limit was found on Pest 3 in `UAMS-Web/uamswp-migration-api-archive#408`, where the child exits 0 and is scored untested instead.

**What the patch does.** `MutationTest::start()` now takes its filter from `MutationTest::childFilter()`. A filter that compiles, and on Windows also fits the command line, is unchanged. Otherwise the filter is widened to the covering test **classes** (`Class::` per class). That only adds tests that exercise the same code, so a kill they score is still real. When even the class-level filter does not fit or does not compile, the run throws, naming the mutant, rather than score a mutant it cannot test. The compile check runs the candidate through `preg_match` the way PHPUnit wraps it, under a temporary error handler rather than `@`, because a test runner's handler still records a warning `@` suppressed; the previous handler is restored afterwards. The PCRE half ports `UAMS-Web/uamswp-migration-api-archive#413`, which compiles the filter as `/.../i` for the PHPUnit that repository runs; PHPUnit 13 here wraps it in `{...}i`, so this copy compiles it that way. The length is estimated generously (every argument counted as quoted, plus 1,024 characters of headroom for the `cmd.exe` wrapper, the output redirections and the batch launcher), because over-estimating only widens a filter.

**Re-measured with the patch** (macOS, PHP 8.4.23, PCOV, `693e44b8e`). The same `AugmentedAssetHook.php` run (`tests/Feature/Support --parallel --processes=8 --mutate --covered-only --no-cache`) gave `3 timeout, 5 tested`, `Score: 100.00%`, a 606.05s mutation phase against a 488.38s base phase. The widened children ran with a class-level filter of 36 classes and 1,106 characters, read from the process table during the run. The three timeouts are scored caught (#2550), so that score is not a clean measurement on its own: a widened child runs every test in its classes serially, which here exceeds the plugin's timeout. Hand children with the class-level filter `AugmentedAssetHookTest::` and the plugin's two environment variables: a byte-identical copy of the source exited 0 (`5 passed`), and a copy with line 50's `!==` inverted exited 1 with a named failing assertion. A hand child with all 46 `tests/Feature/Support` classes as the filter and the identical copy ran 642 tests with no failure before its 5,400s bound stopped it.

**Upstream.** Not checked against `pestphp/pest-plugin-mutate`'s default branch, and no upstream issue has been filed.

**Guard test.** `tests/Feature/Config/MutantFilterLengthPatchTest.php`. One case reads the vendored source; the rest call `childFilter()` with the platform as an argument, so the Windows branch is exercised on any machine and the PCRE branch is exercised both off and on Windows. They cover widening and refusal for each limit, and that the error handler in place before the compile check is the one in place after it.

### `pest-plugin-mutate` mutated-source write (a run scores mutants it never applied)

`patches/pest-plugin-mutate-write-mutated-source.patch` is temporary, tracked by #2812.

**What it fixes.** `Mutation::create()` writes each mutated source into `vendor/pestphp/pest-plugin-mutate/.temp/mutations` with `file_put_contents()` and ignores the result, and nothing creates that directory when it is missing. With the directory gone, no mutant is applied, yet the run still prints a score and exits 0. This is defect 3 of [`pestphp/pest#1942`](https://github.com/pestphp/pest/issues/1942); the plugin's pull request for that issue, [`pestphp/pest-plugin-mutate#46`](https://github.com/pestphp/pest-plugin-mutate/pull/46), covers defects 1 and 2 only.

Measured on macOS / PHP 8.4 / v5.0.2 under `herd coverage` (Xdebug), against `App\Modifiers\SanitizeUrl` with `tests/Unit/Modifiers/SanitizeUrlTest.php`, `--covered-only --no-cache`, the mutation cache cleared before each arm:

| `.temp/mutations` | Unpatched | Patched |
| --- | --- | --- |
| present | not run | `3 untested, 31 tested`, exit 0 |
| removed | a `file_put_contents` warning per mutant, then `34 tested`, `Score: 100.00%`, exit 0 | the directory is created, then `3 untested, 31 tested`, exit 0 |
| a regular file in its place | not run | `RuntimeException: Unable to create the mutations directory [...]`, no score, exit 1 |
| read-only (`0555`) | not run | `RuntimeException: Unable to write the mutated source to [...]`, no score, exit 1 |

The three survivors are the equivalents `SanitizeUrlTest.php` already records, so they are the control. The unpatched figure here is worse than the `0 Mutations` reported on macOS in #1942: every mutant reads as killed.

**The mutation cache hid the same failure.** `MutationGenerator` calls `Mutation::create()` only on a cache miss. The cache under `.temp/pest-mutate-cache` never expires, and a cached mutation points at its file in `.temp/mutations`. So a warm run (the default, and `composer mutate` passes no `--no-cache`) with that directory removed never reached the checked write: each child's mutated file was missing, the child failed, and it was scored a kill. Measured with the write fix alone: a warm run gave `3 untested, 31 tested`; after removing `.temp/mutations`, the next warm run gave `34 tested`, `Score: 100.00%`, exit 0, and did not recreate the directory. With the full patch, the same sequence gave `3 untested, 31 tested` both times and recreated all 34 files.

**What the patch does.**
- The write moves into a new `Mutation::writeModifiedSource($directory, $source)`, which creates the directory when it is missing and throws, naming the path and the PHP error, when the directory cannot be created or the file cannot be written. Warnings raised inside it are captured by a scoped error handler and carried in the exception message, so they are not reported separately. Taking the directory as an argument is what lets the guard test exercise it against a temporary directory.
- `MutationGenerator` uses a cached mutation set only when `Mutation::modifiedSourcesExist()` finds every one of its files; otherwise it regenerates the set, which writes the files again. The patch edits `MutationGenerator.php` after `pest-plugin-mutate-annotation-parsing.patch` does, so it is listed after that patch in `composer.json`.

**Upstream.** Present on the locked `v5.0.2` and on `pestphp/pest-plugin-mutate`'s `5.x` at `d0c8711`. The issue is already filed (#1942); the pull request is batched with the other `pest-plugin-mutate` reports in #2676.

**Retirement condition.** A released `pest-plugin-mutate` whose `Mutation::create()` checks the write and creates its directory, and whose cache does not hand out a mutation whose file is gone. Check the tag, per *Checking whether the fix shipped* in the [`upstream-patch-lifecycle`](../.claude/skills/upstream-patch-lifecycle/SKILL.md) skill.

**Removal checklist.** Remove all of these together, the patch before the package moves:

- `patches/pest-plugin-mutate-write-mutated-source.patch`
- `tests/Feature/Config/MutantModifiedSourceWritePatchTest.php`, which calls `writeModifiedSource()` and `modifiedSourcesExist()` and goes red once the patch is gone
- Its `extra.patches` entry in `composer.json`, then `composer patches-relock --no-interaction` and `composer update --lock --no-interaction`

**Guard test.** `tests/Feature/Config/MutantModifiedSourceWritePatchTest.php`. Two cases read the vendored call sites in `Mutation.php` and `MutationGenerator.php`. The rest call the patched methods against a temporary directory: a missing directory is created; a blocked directory and a blocked file each throw, naming the path; the previous error handler is restored after a write and after a failure; and `modifiedSourcesExist()` is false when any file is missing.

### `pest-plugin-mutate` parallel-runner options (a parallel run scores every mutant killed)

`patches/pest-plugin-mutate-drop-parallel-runner-options.patch` is temporary, tracked by #2926 and #2947. It replaced `pest-plugin-mutate-drop-passthru-php.patch` (#2926), which dropped one of these options.

**What it fixes.** `MutationTest::start()` builds each mutant child from the run's own arguments. The plugin strips its own options (`--parallel`, `--class` and the rest), but it passed through the options that belong to the parallel runner. A mutant child is a plain run: it prints `Unknown option` and exits 2, and `hasFinished()` reads any non-zero exit as a kill, so every mutant of a `--parallel` run given one of these options is scored killed without running a test. `--passthru-php` is how a machine without PCOV gets a coverage driver into the parallel workers, and `--processes` bounds the worker count, so both are ordinary things to pass.

Measured in a plain run (pest-plugin-mutate v5.0.2, macOS, PHP 8.4.25): each of these exits 2 with `Unknown option`, and the patch drops each one:

- with a value: `--max-batch-size`, `-m`, `--max-processes`, `--passthru-php`, `--processes`, `--runner`, `--shard-test-distribution`, `--shard-test-distribution-seed`, `--tmp-dir`
- flags: `--functional`, `--no-test-tokens`, `--verbose`

`-p`, `-v` and `--shard` are accepted by a plain run and are left alone.

The two measured false kills:

| Run | Unpatched | Patched |
| --- | --- | --- |
| `SchemaThingFromRelationshipResolver`, its main test file, `--parallel --passthru-php=…` (#2926) | `993 tested`, `100.00%`, 17.3 s mutation phase | `404 untested, 589 tested`, `59.32%`, 940.4 s |
| `FieldRequiredHelper`, its test file, `--parallel --processes=6 --passthru-php=…` (#2947) | `41 tested`, `100.00%`, 1.19 s mutation phase | `8 untested, 33 tested`, `80.49%`, 47.8 s |

Both patched figures equal a serial run of the same file, which takes no parallel option at all: `589 of 993` for the resolver, and `8 untested, 33 tested` in 135.9 s for `FieldRequiredHelper`.

**What the patch does.** It removes each option above from the arguments a child inherits, in every spelling: `--option=value`, `--option value`, `-m value` and `-m<value>`, and the bare flags. It runs after the run filter is removed (#2570), so neither patch undoes the other. A child never collects coverage or spawns workers, so it needs nothing these options carry; the parallel workers still receive them from the parent run.

**Upstream.** Not checked against `pestphp/pest-plugin-mutate`'s default branch, and no upstream issue has been filed. Whether to report it upstream is a separate question, as #2576 handled it; #2676 is the batch of plugin reports waiting on upstream activity.

**Retirement condition.** A released `pest-plugin-mutate` whose `MutationTest::start()` forwards none of these options to a mutant child.

**Removal checklist.** Remove all of these together, the patch before the package moves:

- `patches/pest-plugin-mutate-drop-parallel-runner-options.patch`
- `tests/Feature/Config/MutantParallelRunnerOptionsPatchTest.php`, which reads the vendored source and goes red once the patch is gone
- Its `extra.patches` entry in `composer.json`, then `composer patches-relock --no-interaction` and `composer update --lock --no-interaction`

**Guard test.** `tests/Feature/Config/MutantParallelRunnerOptionsPatchTest.php`: one case per option, one for the two-argument and `-m` spellings, and one requiring the drop after the run-filter drop and before the child `Process` is built.

### `larastan` console commands registered through the kernel

`patches/larastan-console-kernel-commands.patch` is temporary, tracked by #3168, which removes it. (#3156, which carried it in, is closed.) It is a backport of [`larastan/larastan#2585`](https://github.com/larastan/larastan/pull/2585) onto the locked `v3.10.0`.

**What it fixes.** Since laravel/framework `v13.36.0`, `Illuminate\Foundation\Console\Kernel::load()` and `command()` no longer register their commands through `Artisan::starting()`; they keep them on the kernel and hand them to the kernel's own Artisan application. Larastan's `ConsoleApplicationResolver` builds a standalone Artisan application and reads its commands, so it no longer sees any command discovered in `app/Console/Commands`. `argument()` and `option()` on those commands then fall back to their broad docblock union, and every place that uses the value as a string is reported. Upstream tracks the defect as [`larastan/larastan#2260`](https://github.com/larastan/larastan/issues/2260).

Measured on macOS with #3046's lock merged onto `main` (laravel/framework `v13.37.0`), each run through its own cold `tmpDir`:

| Run | Unpatched | Patched |
| --- | --- | --- |
| `phpstan analyse app/Console/Commands` | 27 errors across 7 files | 0 errors |
| `phpstan analyse` (the whole configured scope) | not run | `[OK] No errors` |

On `main`'s own lock (laravel/framework `v13.35.0`) the commands still reach the standalone application through `Artisan::starting()`, so the patch changes no result there; it is carried now so that the framework update can land.

**What the patch does.** `ConsoleApplicationResolver` asks the bound console kernel for its commands (`Kernel::all()`), which includes both the kernel's own commands and everything registered through `Artisan::starting()`. It falls back to the standalone application when no kernel is bound or the kernel throws, and caches the result for the analysis run.

**Upstream.** Fixed by `larastan/larastan#2585`, merged into the `3.x` branch as `043139b98` on 2026-10-16. `v3.13.1` (published on Packagist 2026-10-16) is that merge commit, and `v3.13.2` (2026-10-23) contains it; `v3.13.0` is 8 commits behind it. Read 2026-10-24 through the GitHub compare API. So a release carries the fix, and the retirement condition below is met. Nothing needs filing upstream.

**Retirement condition.** A released `larastan/larastan` that contains `#2585`. Check the tag, per *Checking whether the fix shipped* in the [`upstream-patch-lifecycle`](../.claude/skills/upstream-patch-lifecycle/SKILL.md) skill.

**Removal checklist.** Remove all of these together, the patch before the package moves:

- `patches/larastan-console-kernel-commands.patch`
- `tests/Feature/Config/LarastanConsoleKernelCommandsPatchTest.php`, which calls the patched lookup and goes red once the patch is gone
- Its two `internalClass` pins in `phpstan.neon.dist`
- Its `extra.patches` entry in `composer.json`, then `composer patches-relock --no-interaction` and `composer update --lock --no-interaction`

**Guard test.** `tests/Feature/Config/LarastanConsoleKernelCommandsPatchTest.php`. In the booted application it calls the patched resolver's command lookup and requires each of the seven commands in `app/Console/Commands` and a command registered through `Artisan::starting()` (`migrate`). With the unpatched resolver restored in `vendor/`, it fails naming the missing patch.

### `symfony/console` Windows null device for the stty restore

<!-- cspell:ignore stty -->

`patches/symfony-console-terminal-input-windows-null-device.patch` is temporary, carried in by #230 and removed by #232. It applies to the locked `v8.1.9`. The defect is diagnosed in `UAMS-Web/wordpress-importer#83`.

**What it fixes.** A `ChoiceQuestion` (a numbered prompt) offers its choices for autocompletion, so `QuestionHelper` reads the answer through `TerminalInputHelper` whenever `stty` is available, which it is in a Git Bash session. `TerminalInputHelper` restores the terminal with `stty <state> 2>/dev/null || stty sane`, in `finish()` and in its signal handler. `shell_exec()` runs that through `cmd.exe` on Windows, which has no `/dev/null`, so `cmd.exe` prints `The system cannot find the path specified.` after every answer and runs `stty sane` instead of the exact restore. `UAMS-Web/wordpress-importer#83` counted 16 of these in one import run.

Measured on Windows 11 Pro 10.0.26200.9711 with Herd PHP 8.4.20 (NTS x64), with a `ChoiceQuestion` answered at a Windows console, `shell_exec()` running through `cmd.exe`, and Git for Windows 2.55.0's `stty` (GNU coreutils 8.32) on `PATH`:

| Run | Unpatched | Patched |
| --- | --- | --- |
| Lines reading `The system cannot find the path specified.` after the answer | 1 | 0 |
| The answer read | `cherry` | `cherry` |

**What the patch does.** Both restores send `stty`'s error output to `NUL` on Windows and to `/dev/null` elsewhere, the same choice `Terminal::hasSttyAvailable()` already makes. On macOS and Linux the command is unchanged.

**Upstream.** [`symfony/symfony#67161`](https://github.com/symfony/symfony/pull/67161), merged into `6.4` on 2026-10-19, fixes `finish()` only. On the `8.1` and `8.2` branches the signal handler's restore still sends its errors to `/dev/null` (read 2026-10-27), and no tag carries even the `finish()` fix: `v8.1.9` is the latest. Offering the signal-handler half upstream is #231.

**Retirement condition.** A released `symfony/console` on the line this application installs whose `TerminalInputHelper` picks the null device by platform at both restores. Check the tag, per *Checking whether the fix shipped* in the [`upstream-patch-lifecycle`](../.claude/skills/upstream-patch-lifecycle/SKILL.md) skill. A release carrying only `#67161` makes the first hunk fail to apply; the patch then has to be cut down to the signal-handler hunk, not removed. The guard test changes with it: upstream writes the `finish()` restore through a `$nullDevice` variable, so its second case must then count one inline restore instead of two.

**Removal checklist.** Remove all of these together, the patch before the package moves:

- `patches/symfony-console-terminal-input-windows-null-device.patch`
- `tests/Feature/Config/SymfonyConsoleWindowsNullDevicePatchTest.php`, which reads the patched vendor source and goes red once the patch is gone
- Its `extra.patches` entry in `composer.json`, then `composer patches-relock --no-interaction` and `composer update --lock --no-interaction`

**Guard test.** `tests/Feature/Config/SymfonyConsoleWindowsNullDevicePatchTest.php`. It requires no `2>/dev/null || stty sane` restore and exactly two platform-chosen restores in the vendored `TerminalInputHelper`. With the unpatched file restored in `vendor/`, both cases fail. It reads the source rather than driving a prompt, because the defect only shows with a real terminal attached.

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
