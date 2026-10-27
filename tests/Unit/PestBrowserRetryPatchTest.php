<?php

declare(strict_types=1);

use Pest\Browser\Execution;
use Pest\Browser\Playwright\Client;
use Pest\Browser\Playwright\Playwright;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * Guards `patches/pest-plugin-browser-per-attempt-timeout.patch`.
 *
 * Remove this file when the patch is removed; see **`pest-plugin-browser`
 * per-attempt retry timeout** in `patches/README.md` for the retirement
 * condition.
 *
 * `Execution::waitForExpectation()` takes a plain callable and drives the retry
 * loop, so the load-bearing behavior can be asserted without a browser: the
 * callback simply records the per-attempt timeout that `usingTimeout()` installed
 * on the client. Unpatched, that value is a hardcoded 1000 regardless of
 * `Playwright::setTimeout()`.
 *
 * There is no backing App\ PHP class, so there is no mutable PHP surface to
 * mutation-test.
 *
 * @command  php artisan test --compact tests/Unit/PestBrowserRetryPatchTest.php
 */
beforeEach(function (): void {
    // Playwright::$timeout is a static singleton shared across the process, so
    // capture it and restore afterwards rather than leaking a value into the
    // browser suite's own configuration.
    $this->originalTimeout = Playwright::timeout();
});

afterEach(function (): void {
    Playwright::setTimeout($this->originalTimeout);
});

it('gives each attempt the configured timeout rather than a hardcoded 1000ms', function (): void {
    Playwright::setTimeout(7_000);

    $perAttemptTimeouts = [];

    Execution::instance()->waitForExpectation(function () use (&$perAttemptTimeouts): string {
        // usingTimeout() installs the per-attempt budget on the client before
        // invoking this callback, so reading it here is reading the attempt budget.
        $perAttemptTimeouts[] = Client::instance()->timeout();

        return 'done';
    });

    // Exactly one attempt, granted essentially the whole configured budget. Asserted
    // as a range, not 7_000 exactly, because the budget is clamped to the window's
    // REMAINING milliseconds and a fraction of one has always elapsed by then.
    // Unpatched this is a hardcoded 1000, so the lower bound is what reds on revert.
    expect($perAttemptTimeouts)->toHaveCount(1)
        ->and($perAttemptTimeouts[0])->toBeGreaterThan(6_900)
        ->and($perAttemptTimeouts[0])->toBeLessThanOrEqual(7_000);
});

it('clamps a later attempt to what is left of the retry window', function (): void {
    // The loop tests its deadline only at the top, so without a clamp an attempt
    // admitted just under the window end would get a fresh FULL budget — letting
    // the loop alone reach 2x the timeout and, with the unconditional post-loop
    // call, a 3x ceiling. The patch documents a ~2x bound, so assert the clamp
    // that makes that true rather than trusting the comment.
    Playwright::setTimeout(3_000);

    $budgets = [];

    // Always failing, so the loop keeps admitting attempts until the window closes.
    try {
        Execution::instance()->waitForExpectation(function () use (&$budgets): void {
            $budgets[] = Client::instance()->timeout();

            usleep(200_000);

            throw new ExpectationFailedException('never satisfied');
        });
    } catch (ExpectationFailedException) {
        // The post-loop call rethrows; the recorded budgets are what this test is after.
    }

    // Drop the final entry: the post-loop `return $callback()` runs outside
    // usingTimeout(), at the restored ambient value, so it is not an attempt budget.
    $attemptBudgets = array_slice($budgets, 0, -1);

    // Several attempts ran, so at least one was admitted mid-window.
    expect(count($attemptBudgets))->toBeGreaterThan(1);

    // Unpatched every attempt is pinned at 1000 regardless of the 3000 configured
    // here, so this is the assertion that reds on a revert.
    expect($attemptBudgets[0])->toBeGreaterThan(2_000);

    // Each later attempt gets strictly less, because the window is draining — that
    // draining IS the clamp.
    foreach (array_slice($attemptBudgets, 1) as $index => $budget) {
        expect($budget)->toBeLessThan($attemptBudgets[$index]);
    }

    // The invariant that makes the documented ~2x bound true: no attempt may be
    // handed more than the window it is running inside.
    foreach ($attemptBudgets as $budget) {
        expect($budget)->toBeLessThanOrEqual(3_000);
    }
});

it('still retries an attempt that fails its expectation', function (): void {
    // A short window keeps the test fast while still allowing several attempts.
    Playwright::setTimeout(2_000);

    $attempts = 0;

    $result = Execution::instance()->waitForExpectation(function () use (&$attempts): string {
        $attempts++;

        // Fail the first two attempts the way a not-yet-rendered assertion does.
        if ($attempts < 3) {
            throw new ExpectationFailedException('not settled yet');
        }

        return 'settled';
    });

    // Retry is load-bearing for the CP smokes, which rely on assertSee()
    // succeeding once an async render settles. The patch must not remove it.
    expect($result)->toBe('settled')
        ->and($attempts)->toBe(3);
});

it('stops retrying and surfaces the failure once the window closes', function (): void {
    Playwright::setTimeout(1_100);

    $attempts = 0;

    // Always failing: the loop must give up rather than spin forever, and the
    // final post-loop call is what surfaces the error to the caller.
    expect(function () use (&$attempts): void {
        Execution::instance()->waitForExpectation(function () use (&$attempts): void {
            $attempts++;

            throw new ExpectationFailedException('never satisfied');
        });
    })->toThrow(ExpectationFailedException::class, 'never satisfied');

    expect($attempts)->toBeGreaterThan(1);
});
