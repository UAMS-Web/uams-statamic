<?php

declare(strict_types=1);

use Pest\Browser\Playwright\Playwright;
use Tests\Support\BrowserAssetFreshness;
use Tests\Support\BrowserWriteIsolation;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Pest bootstrap
|--------------------------------------------------------------------------
|
| Pest runs the entire suite. The PHPUnit class tests were converted in UAMS-Web/uams-statamic-archive#916
| and the CI job switched to `vendor/bin/pest` in UAMS-Web/uams-statamic-archive#997, so PHPUnit survives
| only as the engine Pest is built on (Pest 5 / PHPUnit 13).
|
| The `Unit` + `Feature` testsuites in phpunit.xml are what `php artisan test`
| runs. `tests/Browser` is deliberately NOT registered as a testsuite, so the
| headless Control Panel browser smokes (UAMS-Web/uams-statamic-archive#852) stay out of the default gate
| and are run explicitly:  vendor/bin/pest tests/Browser
|
| The uses() below therefore scopes to Browser only — it raises the Playwright
| timeout for those tests, and binding `Tests\TestCase` gives them the full
| Laravel application (to seed a CP user and serve the real CP). Unit and
| Feature bind their own base classes per-file.
|
*/

// Pin Tia's impact-graph cache to a path inside this checkout (#1992).
//
// By default Pest stores it at `~/.pest/tia/<folder-slug>-<origin-hash>/`, where the
// hash is derived from the git remote. Two checkouts of this repo therefore share one
// cache whenever their directory names slugify to the same string — and `slug()`
// lowercases and collapses runs of non-alphanumerics, so `uams-statamic-a` and
// `uams_statamic_a` collide. Edges are global to that key (`Graph::replaceEdges()`
// takes no branch), so one checkout's edge set would drive another's affected-set
// computation. The failure is silent and is the one UAMS-Web/uams-statamic-archive#1717 adopted Tia to avoid: a
// green run that executed nothing, indistinguishable from a fast one.
//
// Today linked worktrees are separated by accident rather than design — `.git` is a
// file there, so `Storage::rawOriginUrl()` cannot read `.git/config` and the hash
// falls back to the path. Upstream documents the opposite intent ("multiple worktrees
// of the same repository share one cache") and has an open PR to make it so, so that
// accident is not something to rely on. See `pestphp/pest#1820` and our comment there.
//
// A project-relative directory sidesteps the key entirely: `Storage::tempDir()`
// short-circuits to `$projectRoot.'/'.$directory`, so every worktree and every clone
// gets its own cache by construction, whatever upstream decides. Requires pest
// ^5.1.1, which added the setting (`pestphp/pest#1787`).
//
// `tests/.pest/` is already git-ignored as Pest's profile directory, so this needs no
// new ignore rule, and `Storage::purge()` removes only its own subtree — the
// `--update-shards` profile beside it is untouched.
pest()->tia()->directory('tests/.pest/tia');

// Raise the Playwright action timeout for browser smokes (default 5s). The CP is
// a heavy Vue SPA served by an in-process HTTP server that shares CPU with the
// test process; under load (a busy CI runner or dev box) a render can exceed 5s,
// so the default timeout flakes even trivial pages. 30s gives ample headroom
// while still failing a genuinely broken page (UAMS-Web/uams-statamic-archive#1009). Set per-test so a nested
// usingTimeout() restore can't leave a lower value in place.
//
// Isolation is armed HERE rather than in each smoke's own beforeEach so a browser
// test cannot forget it (UAMS-Web/uams-statamic-archive#1920). A test that persists a collection, blueprint or
// CP user strands it in a tracked tree whenever the process dies before its
// afterEach, and the leftover then fails the NEXT run's UAMS-Web/uams-statamic-archive#1889 drift gate or
// UAMS-Web/uams-statamic-archive#1873 content-litter gate. A `uses()`-level beforeEach is chained AHEAD of the
// file-level one (`ChainableClosure::bound($this->__beforeEach, $beforeEach)` in
// `Testable::setUp()`), so the throwaway roots are in place before any smoke
// writes its first fixture.
//
// The matching `end()` is deliberately NOT registered here, because `uses()`
// -level hooks run first in BOTH directions — the same `bound()` call in
// `Testable::tearDown()` puts this closure AHEAD of the file's own afterEach, not
// around it. Registering the teardown here would restore the real directories and
// delete the throwaway root BEFORE each smoke's cleanup ran, pointing every one of
// those deletes at tracked source. It lives in `Tests\TestCase::tearDown()`
// instead, which Pest calls via `parent::tearDown()` inside the `finally` that
// follows the whole afterEach chain — the one hook that genuinely runs last.
// See Tests\Support\BrowserWriteIsolation for what moves where, and why
// relocation rather than an ignore rule.
uses(TestCase::class)
    ->beforeEach(function () {
        // Refuse the run outright when the built assets are older than their
        // sources (UAMS-Web/uams-statamic-archive#2252). A browser fixture resolves its script tag through
        // `public/build/manifest.json`, so a stale build makes the suite report a
        // plausible behavioral failure — an undefined component, a binding that
        // never fires — with nothing saying the assets are old. It read as a
        // regression in a just-merged fix on two machines in one evening.
        //
        // Armed here rather than in `scripts/local-ci.mjs`: that chain runs
        // `build` at job 6 and `browser` at job 12, so a gated run cannot reach
        // this state. The exposure is a bare `vendor/bin/pest tests/Browser`,
        // which is how a browser test is normally written.
        BrowserAssetFreshness::assertFresh();

        Playwright::setTimeout(30_000);
        BrowserWriteIsolation::begin();
    })
    ->in('Browser');

// Reusable Control Panel browser-smoke helpers (seedCpSuperUser / loginToCp /
// assertNoAppJavaScriptErrors). See the file header for the smoke recipe.
require_once __DIR__.'/Support/CpSmoke.php';

// Shared mockTermRepository() helper for the taxonomy-term validation-rule tests.
require_once __DIR__.'/Support/TermRepositoryMock.php';

// Shared ensureAudioSeriesTaxonomyRegistered() helper — keeps a term's taxonomy()
// non-null on the schema render path for tests that resolve `audio_series` terms.
require_once __DIR__.'/Support/AudioSeriesTaxonomy.php';

// Accessibility (axe-core) smoke helpers (runAxe / assertNoSeriousAccessibilityViolations).
require_once __DIR__.'/Support/Axe.php';

// Shared `clinical_locations` JSON-LD fixtures and node builders, used by the five
// SchemaJsonLdLocation* suites split out of the former single class in UAMS-Web/uams-statamic-archive#1797.
require_once __DIR__.'/Support/SchemaLocationFixtures.php';

// Shared useProductionJsonLdFragmentCacheRegime() helper — puts a SchemaJsonLd* suite
// on production's fragment-cache regime (page output off, fragments on, bust rotated
// per test) instead of the master switch that silently took both down (UAMS-Web/uams-statamic-archive#1842).
require_once __DIR__.'/Support/SchemaJsonLdCacheRegime.php';

// Shared throwableFrom() helper — the single-invocation, whole-Throwable-tree way to
// assert an operation completed cleanly. See the file header for why
// `->not->toThrow(…)` cannot express that safely (UAMS-Web/uams-statamic-archive#1759).
require_once __DIR__.'/Support/Throwables.php';

// Shared pendingArtisan() helper — resolves `artisan()`'s `PendingCommand|int`
// union by control flow, so the five console suites need no `assert()` narrowing
// (UAMS-Web/uams-statamic-archive#1733). See the file header for why `assert()` was the wrong instrument.
require_once __DIR__.'/Support/PendingArtisan.php';

// Shared positionOf() helper — narrows strpos()'s `int|false` by control flow so
// ordering assertions ("X renders before Y") need no `assert()` (UAMS-Web/uams-statamic-archive#1733).
require_once __DIR__.'/Support/StringPositions.php';

// Shared mustBe()/mustBeString()/mustExist()/makeForm() narrowing helpers — the
// rest of UAMS-Web/uams-statamic-archive#1733's sweep, which its `^\s*assert(` search never matched: the 157
// fully-qualified `\assert(` narrowings across the suite (UAMS-Web/uams-statamic-archive#1865).
require_once __DIR__.'/Support/TypeNarrowing.php';

// Shared runningUnderMutate() helper: the typed `--mutate` check the mutation-hardened
// suites use to pad a mutant child's timeout (#2834).
require_once __DIR__.'/Support/MutateRun.php';
