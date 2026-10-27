<?php

declare(strict_types=1);

use Tests\Support\ThrowawayRoot;

/**
 * The throwaway-root segment that keeps two test runs off each other's fixture
 * directories (UAMS-Web/uams-statamic-archive#1957).
 *
 * The property under test is *uniqueness per process identity*, not the spelling
 * of any one segment — asserting the literal `seq` is what let the serial case
 * stay shared through UAMS-Web/uams-statamic-archive#1136. So the pure derivation is exercised with simulated
 * identities, and the live entry point is pinned to it rather than to a string.
 *
 * Framework-free (no `uses(TestCase)`): the class touches nothing but `getenv`
 * and `getmypid`, so booting the app would only slow it down.
 */
it('gives two process identities two different segments', function (): void {
    // The UAMS-Web/uams-statamic-archive#1957 bug in one assertion: both of these used to be the literal `seq`,
    // so two serial runs in one worktree shared every throwaway root and each
    // one's setUp wiped the other's fixtures mid-test.
    expect(ThrowawayRoot::segmentFor(false, 4077))
        ->not->toBe(ThrowawayRoot::segmentFor(false, 5150));
});

it('gives one process identity a stable segment', function (): void {
    // setUp creates the directory and tearDown deletes it, so a segment that
    // varied per call would leak a directory per test rather than per run.
    expect(ThrowawayRoot::segmentFor(false, 4077))
        ->toBe(ThrowawayRoot::segmentFor(false, 4077));
});

it('names the process in the segment so a stray directory can be traced', function (): void {
    expect(ThrowawayRoot::segmentFor(false, 4077))->toBe('seq-4077');
});

it('combines the worker token with the process id rather than choosing between them', function (): void {
    // UAMS-Web/uams-statamic-archive#1960. UAMS-Web/uams-statamic-archive#1957 keyed the parallel branch on the token alone, which reads as
    // per-run isolation but is not: TEST_TOKEN is the worker INDEX, 1..N, and
    // repeats across independent runs.
    expect(ThrowawayRoot::segmentFor('7', 4077))->toBe('7-4077');
});

it('separates worker N of one band from worker N of another', function (): void {
    // The regression UAMS-Web/uams-statamic-archive#1960 exists for, stated as the property rather than as a
    // literal: same worker index, two live processes, two roots. Keying on the
    // token alone made both of these '7', and each band's setUp/tearDown then
    // deleted the other's fixtures mid-test.
    expect(ThrowawayRoot::segmentFor('7', 4077))
        ->not->toBe(ThrowawayRoot::segmentFor('7', 5188));
});

it('keeps the worker index readable in the segment', function (): void {
    // A path is a debugging aid as much as an isolation mechanism — a failing
    // worker has to be placeable from the directory it left behind. Pinned as a
    // prefix rather than a full-string match so the process half can change.
    expect(ThrowawayRoot::segmentFor('7', 4077))->toStartWith('7-');
});

it('treats an empty TEST_TOKEN as unset', function (): void {
    // `getenv()` returns '' for a variable that is set but empty, which is not a
    // worker identity — falling through to the pid is the safe reading.
    expect(ThrowawayRoot::segmentFor('', 4077))->toBe('seq-4077');
});

it('still separates two processes when the pid is unavailable', function (): void {
    // `getmypid()` is documented as able to return false. A shared constant there
    // would quietly reinstate the collision, so the fallback stays unique.
    expect(ThrowawayRoot::segmentFor(false, false))
        ->not->toBe(ThrowawayRoot::segmentFor(false, false));
});

it('derives the live segment from this process rather than a fixed string', function (): void {
    // The wiring pin: `segment()` must consult the same two inputs the pure
    // derivation takes. Rewriting it to return a constant reds this.
    expect(ThrowawayRoot::segment())
        ->toBe(ThrowawayRoot::segmentFor(getenv('TEST_TOKEN'), getmypid()));
});

it('returns the same live segment every time it is called', function (): void {
    expect(ThrowawayRoot::segment())->toBe(ThrowawayRoot::segment());
});

it('is called inline by every support base that owns a throwaway root', function (string $file): void {
    // Without this, reverting a call site to the pre-#1957 `getenv('TEST_TOKEN')
    // ?: 'seq'` leaves the whole suite green — the helper's own tests keep passing
    // because nothing requires anyone to CALL it. Two of the four are also pinned
    // behaviorally (StacheIsolatedTestCaseTest, CollectionTreeIsolationTest); this
    // covers all four, and covers the one property the isolation lint depends on:
    // the call must be INLINE, since Rule B reads the storage_path() call's own
    // tokens and a hoisted local hides it.
    $source = mustNotBeFalse(
        file_get_contents(__DIR__.'/../../Support/'.$file),
        "the source of tests/Support/{$file}",
    );

    // Assembled from split literals so this file cannot become a subject of the
    // guard it is protecting, exactly as ParallelSafeTestsGuardTest does.
    $call = 'storage'.'_path(';
    $inline = 'ThrowawayRoot::'.'segment()';

    expect($source)->toContain($inline)->toContain($call);

    // The helper must appear INSIDE a storage_path( call, not merely somewhere in
    // the file — a hoisted `$segment = ThrowawayRoot::segment();` would satisfy a
    // bare toContain while defeating Rule B.
    $offset = 0;
    $inlineFound = false;

    while (($open = strpos($source, $call, $offset)) !== false) {
        $close = strpos($source, ');', $open);
        $offset = $open + strlen($call);

        if ($close !== false && str_contains(substr($source, $open, $close - $open), $inline)) {
            $inlineFound = true;

            break;
        }
    }

    expect($inlineFound)->toBeTrue();
})->with([
    'StacheIsolatedTestCase.php',
    'SchemaFixtureStacheTestCase.php',
    'CollectionTreeIsolatedTestCase.php',
    'BrowserWriteIsolation.php',
]);

it('holds the live segment steady even if the environment changes mid-process', function (): void {
    // `segment()` memoizes, so a `putenv` after the first call cannot repoint a
    // root that `setUp` has already created — `tearDown` must be able to delete
    // the same directory. Neither TEST_TOKEN nor the pid changes during a real
    // run, so the memo costs nothing and removes a whole class of mid-run
    // path changes.
    //
    // Mutates the environment, so it restores whatever this worker had: a
    // paratest worker runs its tests in one process, and a leaked token would
    // follow every later test.
    $before = ThrowawayRoot::segment();
    $original = getenv('TEST_TOKEN');

    try {
        putenv('TEST_TOKEN=probe-1701');

        expect(ThrowawayRoot::segment())->toBe($before);
    } finally {
        if (is_string($original)) {
            putenv('TEST_TOKEN='.$original);
        } else {
            putenv('TEST_TOKEN');
        }
    }

    expect(getenv('TEST_TOKEN'))->toBe($original);
    expect(ThrowawayRoot::segment())->toBe($before);
});

it('derives that live segment from both identities, not just the pid', function (): void {
    // The wiring pin that the memo test above cannot give: whatever `segment()`
    // cached, it has to be what the pure derivation produces for this process.
    // Dropping the token from `segment()` would still satisfy the memo test.
    expect(ThrowawayRoot::segment())
        ->toBe(ThrowawayRoot::segmentFor(getenv('TEST_TOKEN'), getmypid()));
});
