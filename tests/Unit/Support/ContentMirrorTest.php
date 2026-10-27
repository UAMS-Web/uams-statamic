<?php

declare(strict_types=1);

use App\Support\ContentMirror;
use Illuminate\Filesystem\Filesystem;

/**
 * The staleness rules behind the test env's disposable `content/` mirror (UAMS-Web/uams-statamic-archive#1916).
 *
 * Exercised on a throwaway pair of directories rather than the real mirror,
 * which every other test in the process is reading — deleting and re-copying
 * that tree to assert a rule about it would be the very hazard the rule exists
 * to prevent. `ContentMirror` takes both paths for exactly this reason.
 *
 * The two entry points differ in one place and it is the whole design, so both
 * halves are pinned: `rebuildIfStale()` treats a drifted mirror as last band's
 * litter and replaces it; `ensureExists()` treats the same drift as a
 * concurrent worker's live fixtures and leaves it alone. Swap them and a
 * booting worker deletes files another worker is mid-test on.
 *
 * A rebuild is detected by an **empty directory** planted in the mirror.
 * `allFiles()` cannot see it, so it does not itself make the mirror stale — and
 * a rebuild deletes the mirror wholesale, so its survival is an exact,
 * timing-free answer to "did this re-copy?".
 *
 * @command  php artisan test --compact tests/Unit/Support/ContentMirrorTest.php
 */
$mirrorRoot = null;

beforeEach(function () use (&$mirrorRoot): void {
    $mirrorRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'content-mirror-test-'.uniqid();

    $files = new Filesystem;
    $files->ensureDirectoryExists(contentMirrorSource($mirrorRoot).'/collections/pages');
    $files->put(contentMirrorSource($mirrorRoot).'/collections/pages.yaml', "title: Pages\n");
    $files->put(contentMirrorSource($mirrorRoot).'/collections/pages/kirk.md', "---\ntitle: Kirk\n---\n");
    $files->put(contentMirrorSource($mirrorRoot).'/.gitignore', "spock\n");
});

afterEach(function () use (&$mirrorRoot): void {
    if (\is_string($mirrorRoot)) {
        (new Filesystem)->deleteDirectory($mirrorRoot);
    }
});

/** The throwaway stand-in for the `content/` submodule. */
function contentMirrorSource(string $root): string
{
    return $root.DIRECTORY_SEPARATOR.'source';
}

/** The throwaway stand-in for `storage/framework/testing/content-mirror`. */
function contentMirrorTarget(string $root): string
{
    return $root.DIRECTORY_SEPARATOR.'mirror';
}

/**
 * Plant an empty directory in the mirror; it survives a reuse and dies in a
 * rebuild, and is invisible to the fingerprint either way.
 */
function contentMirrorPlantRebuildProbe(string $root): void
{
    (new Filesystem)->ensureDirectoryExists(contentMirrorTarget($root).'/enterprise-ncc-1701');
}

/** Whether the probe survived — true means the mirror was reused, not rebuilt. */
function contentMirrorWasReused(string $root): bool
{
    return (new Filesystem)->isDirectory(contentMirrorTarget($root).'/enterprise-ncc-1701');
}

it('copies the source into an absent mirror', function () use (&$mirrorRoot): void {
    /** @var string $mirrorRoot */
    $files = new Filesystem;

    $failure = ContentMirror::rebuildIfStale($files, contentMirrorSource($mirrorRoot), contentMirrorTarget($mirrorRoot));

    expect($failure)->toBeNull()
        ->and($files->get(contentMirrorTarget($mirrorRoot).'/collections/pages/kirk.md'))->toContain('Kirk')
        // Hidden files are copied by `copyDirectory()`, so they must be
        // fingerprinted too — a `.gitignore` change has to make the mirror stale.
        ->and($files->exists(contentMirrorTarget($mirrorRoot).'/.gitignore'))->toBeTrue()
        ->and($files->exists(contentMirrorTarget($mirrorRoot).'.stamp'))->toBeTrue();
});

it('reuses a mirror that still matches its source', function () use (&$mirrorRoot): void {
    /** @var string $mirrorRoot */
    $files = new Filesystem;

    ContentMirror::rebuildIfStale($files, contentMirrorSource($mirrorRoot), contentMirrorTarget($mirrorRoot));
    contentMirrorPlantRebuildProbe($mirrorRoot);

    $failure = ContentMirror::rebuildIfStale($files, contentMirrorSource($mirrorRoot), contentMirrorTarget($mirrorRoot));

    expect($failure)->toBeNull()
        ->and(contentMirrorWasReused($mirrorRoot))->toBeTrue();
});

it('rebuilds when the source has moved', function () use (&$mirrorRoot): void {
    /** @var string $mirrorRoot */
    $files = new Filesystem;

    ContentMirror::rebuildIfStale($files, contentMirrorSource($mirrorRoot), contentMirrorTarget($mirrorRoot));
    contentMirrorPlantRebuildProbe($mirrorRoot);

    $files->put(contentMirrorSource($mirrorRoot).'/collections/pages/uhura.md', "---\ntitle: Uhura\n---\n");

    $failure = ContentMirror::rebuildIfStale($files, contentMirrorSource($mirrorRoot), contentMirrorTarget($mirrorRoot));

    expect($failure)->toBeNull()
        ->and(contentMirrorWasReused($mirrorRoot))->toBeFalse()
        ->and($files->exists(contentMirrorTarget($mirrorRoot).'/collections/pages/uhura.md'))->toBeTrue();
});

it('rebuilds when a previous run left litter in the mirror', function () use (&$mirrorRoot): void {
    /** @var string $mirrorRoot */
    $files = new Filesystem;

    ContentMirror::rebuildIfStale($files, contentMirrorSource($mirrorRoot), contentMirrorTarget($mirrorRoot));

    // A test fixture stranded by a band killed at its bound. Reusing a mirror in
    // this state is how a stray file becomes boot-visible input the next warm
    // indexes as real content (UAMS-Web/uams-statamic-archive#1845) — relocated out of `content/`, but not gone.
    $files->put(contentMirrorTarget($mirrorRoot).'/collections/pages/zz-stranded-fixture.md', "---\ntitle: Litter\n---\n");

    $failure = ContentMirror::rebuildIfStale($files, contentMirrorSource($mirrorRoot), contentMirrorTarget($mirrorRoot));

    expect($failure)->toBeNull()
        ->and($files->exists(contentMirrorTarget($mirrorRoot).'/collections/pages/zz-stranded-fixture.md'))->toBeFalse();
});

it('leaves a drifted mirror alone when only ensuring it exists', function () use (&$mirrorRoot): void {
    /** @var string $mirrorRoot */
    $files = new Filesystem;

    ContentMirror::rebuildIfStale($files, contentMirrorSource($mirrorRoot), contentMirrorTarget($mirrorRoot));

    // Mid-band this is not litter — it is a concurrent worker's live fixture.
    // A booting process that "helpfully" restored the mirror here would delete
    // it out from under a test that is still running.
    $files->put(contentMirrorTarget($mirrorRoot).'/collections/pages/scotty.md', "---\ntitle: Scotty\n---\n");
    contentMirrorPlantRebuildProbe($mirrorRoot);

    $failure = ContentMirror::ensureExists($files, contentMirrorSource($mirrorRoot), contentMirrorTarget($mirrorRoot));

    expect($failure)->toBeNull()
        ->and(contentMirrorWasReused($mirrorRoot))->toBeTrue()
        ->and($files->exists(contentMirrorTarget($mirrorRoot).'/collections/pages/scotty.md'))->toBeTrue();
});

it('builds the mirror when only ensuring it exists and it is absent', function () use (&$mirrorRoot): void {
    /** @var string $mirrorRoot */
    $files = new Filesystem;

    $failure = ContentMirror::ensureExists($files, contentMirrorSource($mirrorRoot), contentMirrorTarget($mirrorRoot));

    expect($failure)->toBeNull()
        ->and($files->exists(contentMirrorTarget($mirrorRoot).'/collections/pages/kirk.md'))->toBeTrue();
});

it('treats an unstamped mirror as unfinished and rebuilds it', function () use (&$mirrorRoot): void {
    /** @var string $mirrorRoot */
    $files = new Filesystem;

    // What a build killed partway leaves: a directory, some of the tree, and no
    // stamp — because `rebuild()` removes the stamp before it starts copying.
    // Testing only `isDirectory()` would call this present and hand the process
    // a starved Stache, the same shape as an incomplete snapshot copy (UAMS-Web/uams-statamic-archive#1740).
    $files->ensureDirectoryExists(contentMirrorTarget($mirrorRoot).'/collections/pages');
    $files->put(contentMirrorTarget($mirrorRoot).'/collections/pages.yaml', "title: Half\n");

    $failure = ContentMirror::ensureExists($files, contentMirrorSource($mirrorRoot), contentMirrorTarget($mirrorRoot));

    expect($failure)->toBeNull()
        ->and($files->exists(contentMirrorTarget($mirrorRoot).'/collections/pages/kirk.md'))->toBeTrue()
        ->and($files->exists(contentMirrorTarget($mirrorRoot).'.stamp'))->toBeTrue();
});

it('refuses to keep a copy that silently dropped a file', function () use (&$mirrorRoot): void {
    /** @var string $mirrorRoot */

    // ---
    // The failure `copyDirectory()`'s return value cannot see: a `copy()` that
    // reports success and writes nothing. That is how a worker ran a whole band
    // against a two-file Stache (UAMS-Web/uams-statamic-archive#1740) — and the stakes are higher here, since
    // this tree IS the content, so a short copy is a checkout that quietly lost
    // entries while every test reading one fails pointing somewhere else.
    // ---
    $files = new class extends Filesystem
    {
        public function copy($path, $target): bool
        {
            return str_ends_with(str_replace('\\', '/', (string) $path), 'kirk.md')
                ? true
                : parent::copy($path, $target);
        }
    };

    $failure = ContentMirror::rebuildIfStale($files, contentMirrorSource($mirrorRoot), contentMirrorTarget($mirrorRoot));

    expect($failure)->toContain('files missing')
        ->and($failure)->toContain('collections/pages/kirk.md')
        // Removed, not left behind. A partial tree carries no stamp and would be
        // rebuilt next time — but a partial tree is precisely what must never be
        // readable in the meantime.
        ->and((new Filesystem)->isDirectory(contentMirrorTarget($mirrorRoot)))->toBeFalse();
});

it('refuses to mirror a present but empty source', function () use (&$mirrorRoot): void {
    /** @var string $mirrorRoot */
    $files = new Filesystem;

    // An uninitialized submodule leaves its mount point present and empty. Left
    // unchecked, that stamps an empty mirror as complete and `ensureExists()`
    // accepts it from then on.
    $empty = $mirrorRoot.DIRECTORY_SEPARATOR.'empty-source';
    $files->ensureDirectoryExists($empty);

    $failure = ContentMirror::rebuildIfStale($files, $empty, contentMirrorTarget($mirrorRoot));

    expect($failure)->toContain('the directory is empty')
        ->and($files->exists(contentMirrorTarget($mirrorRoot).'.stamp'))->toBeFalse();
});

it('refuses to rebuild when the mirror and the source are the same directory', function () use (&$mirrorRoot): void {
    /** @var string $mirrorRoot */
    $files = new Filesystem;

    // The negative control, and the only failure here that destroys data rather
    // than a copy of it: `rebuild()` opens with `deleteDirectory($mirror)`, so a
    // mirror that resolved to its own source would wipe `content/` outright.
    // That is not hypothetical — re-rooting the stores against a cache that
    // still held real paths deleted a committed 166-line tree file during this
    // ticket's spike.
    $failure = ContentMirror::rebuildIfStale($files, contentMirrorSource($mirrorRoot), contentMirrorSource($mirrorRoot));

    expect($failure)->toContain('same directory')
        ->and($files->exists(contentMirrorSource($mirrorRoot).'/collections/pages/kirk.md'))->toBeTrue();
});

it('refuses when there is nothing to mirror', function () use (&$mirrorRoot): void {
    /** @var string $mirrorRoot */
    $files = new Filesystem;

    $failure = ContentMirror::rebuildIfStale(
        $files,
        contentMirrorSource($mirrorRoot).'-does-not-exist',
        contentMirrorTarget($mirrorRoot)
    );

    // An uninitialized `content/` submodule is the common cause, and the message
    // names the fix — a wall of null-method errors across unrelated suites is
    // what this replaces.
    expect($failure)->toContain('git submodule update --init content')
        ->and($files->isDirectory(contentMirrorTarget($mirrorRoot)))->toBeFalse();
});

it('reports no source drift when the mirror was built from the current source', function () use (&$mirrorRoot): void {
    /** @var string $mirrorRoot */
    $files = new Filesystem;

    ContentMirror::rebuildIfStale($files, contentMirrorSource($mirrorRoot), contentMirrorTarget($mirrorRoot));

    expect(ContentMirror::sourceDrift($files, contentMirrorSource($mirrorRoot), contentMirrorTarget($mirrorRoot)))
        ->toBeNull();
});

it('reports source drift when the source changed after the mirror was built', function () use (&$mirrorRoot): void {
    /** @var string $mirrorRoot */
    $files = new Filesystem;

    ContentMirror::rebuildIfStale($files, contentMirrorSource($mirrorRoot), contentMirrorTarget($mirrorRoot));

    // The UAMS-Web/uams-statamic-archive#509 shape: a file removed from `content/` after the mirror was built.
    // Restoring it later does not help, because nothing reconsiders the mirror —
    // which is the two hours this guard exists to save.
    $files->delete(contentMirrorSource($mirrorRoot).'/collections/pages/kirk.md');

    $drift = ContentMirror::sourceDrift($files, contentMirrorSource($mirrorRoot), contentMirrorTarget($mirrorRoot));

    expect($drift)->toBeString()
        ->and($drift)->toContain('test:seed-stache');
});

it('does not report a worker fixture written into the mirror as source drift', function () use (&$mirrorRoot): void {
    /** @var string $mirrorRoot */
    $files = new Filesystem;

    ContentMirror::rebuildIfStale($files, contentMirrorSource($mirrorRoot), contentMirrorTarget($mirrorRoot));

    // Mirror-side drift is every ordinary band: this is where fixtures land.
    // Reporting it would red every run, and is why the comparison is one-sided.
    $files->put(contentMirrorTarget($mirrorRoot).'/collections/pages/scotty.md', "---\ntitle: Scotty\n---\n");

    expect(ContentMirror::sourceDrift($files, contentMirrorSource($mirrorRoot), contentMirrorTarget($mirrorRoot)))
        ->toBeNull();
});

it('reports no source drift while a rebuild is in flight and the stamp is gone', function () use (&$mirrorRoot): void {
    /** @var string $mirrorRoot */
    $files = new Filesystem;

    ContentMirror::rebuildIfStale($files, contentMirrorSource($mirrorRoot), contentMirrorTarget($mirrorRoot));

    // `rebuild()` removes the stamp before it copies, so an absent stamp means a
    // build is running or was killed. Failing here would red a band for a build
    // that is working correctly; `ensureExists()` owns that case.
    $files->delete(contentMirrorTarget($mirrorRoot).'.stamp');

    expect(ContentMirror::sourceDrift($files, contentMirrorSource($mirrorRoot), contentMirrorTarget($mirrorRoot)))
        ->toBeNull();
});
