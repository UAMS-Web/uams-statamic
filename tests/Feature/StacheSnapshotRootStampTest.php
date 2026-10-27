<?php

declare(strict_types=1);

use App\Support\ContentRoot;
use App\Support\StacheSeedStamp;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class)->group('serial');

/**
 * The Stache snapshot records which content root it indexes, and a process whose
 * stores read a different one refuses to seed from it (UAMS-Web/uams-statamic-archive#1916).
 *
 * This guards a **destructive** state, not a slow one. A snapshot holds absolute
 * paths; a store whose directory disagrees with its cached view is the mixed
 * state UAMS-Web/uams-statamic-archive#1873 documents, where `ExistsAsFile::writeFile()` recomputes
 * `buildPath()` and `File::delete()`s the `initialPath` it no longer matches.
 *
 * Since the test env roots content at a mirror, an ordinary branch switch reaches
 * it: build a snapshot on a branch without the mirror (it indexes `content/`),
 * check out one with it (the stores read the mirror), run anything that saves. It
 * took `content/trees/collections/default/pages.yaml` — 166 committed lines —
 * twice before this stamp existed, the second time via nothing more exotic than
 * running `main`'s suite and checking the branch back out.
 *
 * Runs in the `serial` group: it rewrites the shared stamp file.
 *
 * @command  php artisan test --compact tests/Feature/StacheSnapshotRootStampTest.php
 */
it('stamps the snapshot with the content root it was warmed against', function (): void {
    // A band seeds before it runs, so the stamp is present — and its absence is a
    // finding rather than grounds to skip, since it would mean every worker in
    // this run paid a full warm instead of a copy.
    expect(File::exists(StacheSeedStamp::path()))->toBeTrue()
        ->and(trim(File::get(StacheSeedStamp::path())))->toBe(ContentRoot::path(Config::string('app.env')));
});

it('accepts a stamp naming the root this process reads', function (): void {
    // The positive half, and not a formality: a predicate that rejected *every*
    // snapshot would satisfy both negative cases below while quietly costing a
    // ~168s warm per worker — the regression UAMS-Web/uams-statamic-archive#1581 exists to prevent.
    expect(StacheSeedStamp::matches(new Filesystem, ContentRoot::path(Config::string('app.env'))))->toBeTrue();
});

it('rejects a stamp naming a different content root', function (): void {
    stacheSeedStampPreserved(function (): void {
        // Exactly what a branch switch leaves: a snapshot indexing the submodule
        // while the stores read the mirror.
        File::put(StacheSeedStamp::path(), base_path('content'));

        expect(StacheSeedStamp::matches(new Filesystem, ContentRoot::path(Config::string('app.env'))))->toBeFalse();
    });
});

it('rejects a snapshot carrying no stamp at all', function (): void {
    stacheSeedStampPreserved(function (): void {
        // A snapshot built before this check existed says nothing about which
        // root it indexes, so it is refused rather than assumed current.
        File::delete(StacheSeedStamp::path());

        expect(StacheSeedStamp::matches(new Filesystem, ContentRoot::path(Config::string('app.env'))))->toBeFalse();
    });
});

it('clears the stamp so a half-built snapshot cannot inherit one', function (): void {
    stacheSeedStampPreserved(function (): void {
        StacheSeedStamp::clear(new Filesystem);

        expect(File::exists(StacheSeedStamp::path()))->toBeFalse();

        // Clearing an already-absent stamp is a no-op rather than a warning —
        // `Filesystem::delete()` reaches `@unlink()`, which the test env's error
        // handler would otherwise promote on the first build of a fresh checkout.
        StacheSeedStamp::clear(new Filesystem);

        expect(File::exists(StacheSeedStamp::path()))->toBeFalse();
    });
});

/**
 * Run a body that rewrites the shared stamp, then put the original back.
 *
 * The stamp is process-wide state every later test in this run boots against, so
 * a leak here would make the rest of the band warm from scratch.
 */
function stacheSeedStampPreserved(Closure $body): void
{
    $original = File::exists(StacheSeedStamp::path()) ? File::get(StacheSeedStamp::path()) : null;

    try {
        $body();
    } finally {
        if ($original === null) {
            File::delete(StacheSeedStamp::path());
        } else {
            File::put(StacheSeedStamp::path(), $original);
        }
    }
}
