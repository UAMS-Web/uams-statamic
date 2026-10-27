<?php

declare(strict_types=1);

use App\Support\ContentMirror;
use Illuminate\Filesystem\Filesystem;
use Tests\Concerns\RequiresContentSubmodule;
use Tests\TestCase;

/**
 * Guard for UAMS-Web/uams-statamic-archive#2092. Reports a content mirror built from a `content/` that no
 * longer matches the one on disk, so a stale tree is named once instead of
 * surfacing as unrelated failures across every suite that reads content.
 *
 * **Why this is a test rather than a warning at boot, which is what the ticket
 * first proposed and what this branch first announced.** A warning was measured
 * and it does not survive the run it matters in: an `fwrite(STDERR, …)` planted
 * in `CreatesApplication`'s boot path appeared **once** under a serial run and
 * **zero** times under `--parallel --processes=4`, because paratest captures
 * worker output per test and a boot happens outside any test. A guard that is
 * invisible in the mode the suite actually runs in is the silence this ticket
 * exists to end, so the report had to move somewhere that cannot be swallowed.
 * A test result is that place.
 *
 * **Why it reports rather than repairs.** `docs/DECISIONS.md` records "Rebuild
 * the mirror on drift at process boot" as a rejected alternative under UAMS-Web/uams-statamic-archive#1916:
 * mid-band that drift is a concurrent worker's live fixtures, so restoring it
 * deletes files another worker is reading. Comparing two fingerprints deletes
 * nothing, so this guard sits inside that decision rather than reversing it —
 * {@see ContentMirror::sourceDrift()} carries the one-sidedness and the blind
 * spots.
 *
 * **What it costs.** One stat walk of `content/`, measured at 0.704 / 0.714 /
 * 0.712 s over 3,234 files, paid once by the single worker that runs this test.
 * The rejected boot-time form would have paid it per worker behind an exclusive
 * lock — ~8.5s serialized onto the front of every band, which is the tax the
 * O(1) comment in `ensureExists()` exists to refuse.
 *
 * @command  php artisan test --compact tests/Feature/ContentMirrorFreshnessTest.php
 */
uses(TestCase::class, RequiresContentSubmodule::class);

it('has a content mirror built from the content/ on disk now', function (): void {
    // The absent-submodule case is the trait's, not this test's: an uninitialized
    // `content/` is a documented skip the whole suite already reports one way, and
    // there is no mirror to judge in it.
    $drift = ContentMirror::sourceDrift(new Filesystem);

    expect($drift)->toBeNull($drift ?? '');
});
