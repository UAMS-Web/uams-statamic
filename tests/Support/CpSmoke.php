<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Control Panel browser-smoke helpers (issue UAMS-Web/uams-statamic-archive#852)
|--------------------------------------------------------------------------
|
| Reusable building blocks for headless CP smoke tests. A fieldtype smoke is
| then three steps: seed a CP user, render the field on a real CP page, and
| assert a clean console.
|
|   $user = seedCpSuperUser($email, $password);
|   $page = loginToCp($email, $password)
|       ->navigate('/cp/collections/<handle>/entries/create/default')
|       ->assertSee('My Field');
|   assertNoAppJavaScriptErrors($page);   // ignores benign browser noise
|
| These are plain global functions (not a trait) so they compose with the
| pest-plugin-browser `visit()` fluent API. Loaded via tests/Pest.php.
|
*/

use Pest\Browser\Api\ArrayablePendingAwaitablePage;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Api\Webpage;
use Pest\Browser\Playwright\Playwright;
use Statamic\Facades\Stache;
use Statamic\Facades\User;

/**
 * Console messages that are browser noise rather than application errors, and
 * so must not fail a smoke. The ResizeObserver "loop" message is emitted by
 * Chromium for virtually any app that observes element size (Statamic's CP
 * does) and signals nothing actionable — it is the canonical false positive.
 *
 * Add to this list only with a comment justifying why the message is benign.
 *
 * @return array<int, string> Substrings; a console error matching any is dropped.
 */
function benignConsolePatterns(): array
{
    return [
        // https://stackoverflow.com/q/49384120 — harmless ResizeObserver timing notice.
        'ResizeObserver loop',
    ];
}

/**
 * Seed a throwaway Control Panel super user and make it resolvable by the
 * in-process HTTP server (which shares this process but dispatches against a
 * Stache snapshot, so a refresh is required after writing the user file).
 *
 * The caller owns cleanup: `$user->delete()` in afterEach.
 *
 * @param  string  $email  Unique email for the user (use a per-test value).
 * @param  string  $password  Plain-text password to log in with.
 * @return Statamic\Contracts\Auth\User The saved user.
 */
function seedCpSuperUser(string $email, string $password)
{
    /** @var Statamic\Auth\User $user */
    $user = User::make();
    $user->email($email);
    $user->makeSuper();
    $user->password($password);
    $user->save();

    Stache::refresh();

    return $user;
}

/**
 * Drive the real CP login form and land on the dashboard. Returns the page so
 * the caller can navigate on to the surface under test.
 *
 * The CP login is a Vue component (Statamic's Login.vue) whose
 * `<form @submit.prevent>` submits the reactive `email`/`password` model — not
 * the raw POST body — so each value must reach the *bound* input, and the submit
 * must settle, before we assert. Two races made this flaky (UAMS-Web/uams-statamic-archive#1009), both guarded
 * here:
 *
 *  1. Field mis-targeting. The pest-plugin-browser fuzzy field guesser resolves
 *     `fill('password', …)` to `[name="password"]` only once Vue has rendered it;
 *     on a cold mount it raced and fell back to a text match, dropping the
 *     password into the autofocused email field (empty password ->
 *     "Authentication failed"). Explicit `input[name="…"]` selectors make
 *     Playwright auto-wait for the real input, so the value always lands in the
 *     bound field; a bounded retry covers any residual cold-mount miss.
 *  2. Redirect timing. press()/click() does not await the submit round-trip +
 *     redirect, and assertPathIs() reads the URL synchronously, so asserting the
 *     dashboard immediately races the redirect. cpReachedPath() waits for it.
 *
 * Returns the page object this helper owns — the one visit() handed back —
 * rather than whatever the trailing assertion returns.
 *
 * pest-plugin-browser annotates PendingAwaitablePage as
 * `@mixin Webpage|AwaitableWebpage` while its fluent assertions actually hand
 * back an AwaitableWebpage, so PHPStan resolves those returns to Webpage and the
 * runtime value fails a declared `: Webpage`. This is NOT a 5.x change — v4.3.1
 * and v5.0.0 are byte-identical here; UAMS-Web/uams-statamic-archive#1623's typing sweep added a `: Webpage`
 * that could never be satisfied, and these four smokes threw
 * `TypeError: … AwaitableWebpage returned` from 2026-07-17 until it was fixed.
 * Nothing surfaced it because the browser suite runs in no active gate (see
 * UAMS-Web/uams-statamic-archive#1727). Do not "restore" the Webpage return type on a future typing pass:
 * not chaining off the assertion is what keeps the signature honest at both
 * the static and runtime levels.
 *
 * @param  string  $email  The seeded user's email.
 * @param  string  $password  The seeded user's plain-text password.
 */
function loginToCp(string $email, string $password): PendingAwaitablePage
{
    $page = visit('/cp/auth/login');

    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $page->fill('input[name="email"]', $email)
            ->fill('input[name="password"]', $password)
            ->press('Continue');

        if (cpReachedPath($page, '/cp/dashboard')) {
            break;
        }
        // Login didn't take (cold-mount race) — we're back on the login form; retry.
    }

    $page->assertPathIs('/cp/dashboard');

    return $page;
}

/**
 * Poll the live page URL until its path matches the target, returning whether it
 * did within the timeout.
 *
 * The browser, the in-process HTTP server, and the test all share one Amp event
 * loop, so each wait() both pauses *and* lets the loop serve the in-flight
 * request — which is what lets a post-submit redirect actually land. We poll
 * rather than assert synchronously (a synchronous read races the redirect, UAMS-Web/uams-statamic-archive#1009).
 *
 * @param  AwaitableWebpage|Webpage|PendingAwaitablePage|ArrayablePendingAwaitablePage  $page  The page returned from visit()/navigate().
 */
function cpReachedPath($page, string $path, float $timeoutSeconds = 10.0): bool
{
    $interval = 0.1;

    for ($waited = 0.0; ; $waited += $interval) {
        if ((parse_url($page->url(), PHP_URL_PATH) ?? '') === $path) {
            return true;
        }

        if ($waited >= $timeoutSeconds) {
            return false;
        }

        $page->wait($interval);
    }
}

/**
 * Wait for the given text to appear on the page, then assert it.
 *
 * Control Panel fieldtypes are Vue components that mount and render their labels
 * *after* the page load event, so a bare assertSee() right after navigate() can
 * race that client render (e.g. the Leaflet-backed `gps` picker's "Paste
 * Coordinates"). Something has to retry.
 *
 * That retry is ALREADY the plugin's job, and this helper's only remaining
 * purpose is to bound it honestly. `$page` is an AwaitableWebpage, whose
 * __call() routes every method not in `nonAwaitableMethods` through
 * Execution::waitForExpectation() whenever Playwright::timeout() > 1000 — and
 * tests/Pest.php sets 30_000 for everything ->in('Browser'), so the gate is
 * open. waitForExpectation() re-runs the assertion until it passes or its window
 * closes, ticking the shared Amp event loop between attempts (Execution::tick()
 * is delay(0)) exactly as an explicit wait() would. So the component still gets
 * its chance to mount.
 *
 * We scope Playwright's timeout to $timeoutSeconds for the single call, making
 * that window BE the declared bound, and restore it afterwards.
 *
 * DO NOT reintroduce an outer polling loop here. The previous implementation
 * counted its own iterations against a fixed 0.1s interval, on the assumption —
 * stated in this docblock, and wrong — that "assertSee() is synchronous: it
 * checks the DOM once and fails immediately". That is true of Webpage::assertSee(),
 * but NOT at this call site, where AwaitableWebpage wraps it in the retry above.
 * Playwright's Frame.querySelector does not auto-wait (verified in the pinned
 * 1.61.1 coreBundle: it races a single selectors.query() rather than using
 * retryWithProgressAndBackoff), so a failing assertSee() throws in milliseconds
 * and waitForExpectation() simply spins on it for the whole 30s window. Each
 * iteration therefore cost ~30s, not ~0.1s, and reaching $waited >= 10.0 took
 * ~101 of them: a missing string was reported after roughly 50 MINUTES rather
 * than the declared ten seconds, which reads as a wedged run, not a failure (UAMS-Web/uams-statamic-archive#1854).
 *
 * A passing lookup is unaffected — measured on a quiet machine, all three call
 * sites hit on the first attempt in 0.63s / 0.84s / 2.37s, well inside the 10s
 * default.
 *
 * Note the plugin bypasses its retry entirely when the window is <= 1000ms, so a
 * $timeoutSeconds at or below 1.0 degrades to a single synchronous check. That is
 * still bounded, but it will race an async render — pass more than a second when
 * waiting on one.
 *
 * Returns nothing: the caller already holds the page, and returning the
 * assertion's own result would inherit the upstream type mismatch described on
 * loginToCp(). Chain further assertions off the page you passed in.
 *
 * @param  AwaitableWebpage|Webpage|PendingAwaitablePage|ArrayablePendingAwaitablePage  $page  The page returned from visit()/navigate().
 * @param  float  $timeoutSeconds  Wall-clock bound for the whole lookup.
 */
function waitToSee($page, string $text, float $timeoutSeconds = 10.0): void
{
    $previousTimeout = Playwright::timeout();

    Playwright::setTimeout((int) round($timeoutSeconds * 1000));

    try {
        $page->assertSee($text);
    } finally {
        Playwright::setTimeout($previousTimeout);
    }
}

/**
 * Assert the page logged no JavaScript console errors, ignoring known-benign
 * browser noise (see benignConsolePatterns). Works by filtering the plugin's
 * in-page error buffer before delegating to its assertion, so any *real*
 * error still fails the test.
 *
 * @param  AwaitableWebpage|Webpage|PendingAwaitablePage|ArrayablePendingAwaitablePage  $page  The page returned from visit()/navigate().
 */
function assertNoAppJavaScriptErrors($page): void
{
    $patterns = json_encode(benignConsolePatterns());

    // Drop benign entries from window.__pestBrowser.jsErrors (the buffer the
    // plugin's assertNoJavaScriptErrors() reads).
    $page->script(
        '(() => { const patterns = '.$patterns.';'
        .' if (window.__pestBrowser && Array.isArray(window.__pestBrowser.jsErrors)) {'
        .' window.__pestBrowser.jsErrors = window.__pestBrowser.jsErrors.filter('
        .' (e) => !patterns.some((s) => String((e && e.message) || e).includes(s))); } })()'
    );

    $page->assertNoJavaScriptErrors();
}
