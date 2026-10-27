<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Pest\Browser\Playwright\Playwright;

/*
 * Guards the wall-clock bound on waitToSee() in tests/Support/CpSmoke.php (UAMS-Web/uams-statamic-archive#1854).
 *
 * The helper's old iteration-counting loop assumed each assertSee() returned in
 * about its 0.1s poll interval. At this call site assertSee() goes through
 * AwaitableWebpage -> Execution::waitForExpectation(), so a MISS costs the whole
 * configured Playwright window (30s, per tests/Pest.php) rather than 0.1s — and
 * reaching `$waited >= 10.0` took ~101 of those, so a string that never appeared
 * was reported after roughly 50 minutes instead of ten seconds.
 *
 * These use a static fixture rather than the Control Panel: the bound is a
 * property of the helper, and driving a CP login to test it would add a minute of
 * unrelated cost and couple the guard to CP behavior it does not care about.
 */

$fixture = '';

beforeEach(function () use (&$fixture): void {
    // 'Deferred Paint' is injected AFTER load, which is the async-render case the
    // helper exists for — a single synchronous check right after visit() misses it.
    $fixture = public_path('__wait_to_see_fixture.html');

    File::put(
        $fixture,
        '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
        .'<title>waitToSee fixture</title></head><body>'
        .'<p>Painted On Arrival</p>'
        .'<div id="deferred"></div>'
        .'<script>setTimeout(function () {'
        .' document.getElementById("deferred").textContent = "Deferred Paint";'
        .' }, 2000);</script>'
        .'</body></html>'
    );
});

afterEach(function () use (&$fixture): void {
    if ($fixture !== '' && File::exists($fixture)) {
        File::delete($fixture);
    }
});

it('reports a string that never appears within its declared timeout', function (): void {
    $page = visit('/__wait_to_see_fixture.html');
    $page->assertSee('Painted On Arrival');

    $timeoutSeconds = 2.0;
    $startedAt = microtime(true);
    $failed = false;

    try {
        waitToSee($page, 'This String Is Never Rendered', $timeoutSeconds);
    } catch (Throwable) {
        $failed = true;
    }

    $elapsed = microtime(true) - $startedAt;

    expect($failed)->toBeTrue('waitToSee() must surface the assertion failure, not swallow it.');

    // Expressed against the DECLARED timeout rather than a hardcoded number, so
    // this states the actual invariant: the helper returns in roughly what it
    // advertises. Measured at 2.039s for this 2.0s declaration — the bound is
    // structural (waitForExpectation compares microtime against a deadline), so
    // the 5x headroom absorbs a loaded machine without weakening the guard.
    // Pre-fix the same call cost ~21 iterations x ~30s = ~630s, which is 63x the
    // ceiling below, so the regression cannot slip back through.
    expect($elapsed)->toBeLessThan($timeoutSeconds * 5);
});

it('still waits out an async render inside the bound', function (): void {
    $page = visit('/__wait_to_see_fixture.html');

    // Injected at ~2s. This passing is the assertion: it proves scoping the
    // Playwright window did not cost the retry the helper exists to provide, so
    // the bound cannot be "achieved" by simply not waiting.
    waitToSee($page, 'Deferred Paint', 10.0);
});

it('restores the ambient Playwright timeout on both the hit and the miss path', function (): void {
    // A leak here is silent and contagious: waitToSee() sets the GLOBAL
    // Playwright timeout, so failing to restore it would re-tune every later
    // browser test in the process from tests/Pest.php's 30s down to this
    // helper's window.
    $ambient = Playwright::timeout();

    $page = visit('/__wait_to_see_fixture.html');

    waitToSee($page, 'Painted On Arrival', 5.0);
    expect(Playwright::timeout())->toBe($ambient);

    try {
        waitToSee($page, 'Also Never Rendered', 2.0);
    } catch (Throwable) {
        // The miss path runs the `finally`; the assertion below is the point.
    }

    expect(Playwright::timeout())->toBe($ambient);
});
