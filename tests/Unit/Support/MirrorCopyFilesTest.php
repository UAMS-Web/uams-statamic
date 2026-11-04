<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Tests\Support\Doubles\StatFailingStreamWrapper;
use Tests\Support\MirrorCopyFiles;
use Tests\Support\ThrowawayRoot;

/**
 * The per-process copy treats a file removed from the shared mirror mid-sync as gone,
 * and still raises on every other failure (UAMS-Web/uams-statamic#558).
 *
 * The race is made deterministic the way TraverserVanishedFileTest makes the
 * traverser's: the listing is materialized first, then the file is removed or its
 * stat broken before the step under test reads it.
 *
 * Framework-free: the class touches only the filesystem, so no app is booted, and
 * the fixtures live under the system temp directory for the same reason.
 *
 * @command  php artisan test --compact tests/Unit/Support/MirrorCopyFilesTest.php
 */
afterEach(function (): void {
    StatFailingStreamWrapper::unregister();
    (new Filesystem)->deleteDirectory(mirrorCopyFilesRoot());
});

it('stamps every listed file when nothing is removed', function (): void {
    $root = mirrorCopyFilesFixture();

    expect(array_keys(MirrorCopyFiles::stamps((new Filesystem)->allFiles($root, true))))
        ->toEqualCanonicalizing(['a.md', 'sub/b.md', 'sub/c.md']);
});

it('skips a file removed between the listing and its stat', function (): void {
    $root = mirrorCopyFilesFixture();
    $listing = (new Filesystem)->allFiles($root, true);

    // Remove a listed file before its stat, as a sibling worker deleting an entry does
    unlink($root.'/sub/b.md');

    expect(array_keys(MirrorCopyFiles::stamps($listing)))
        ->toEqualCanonicalizing(['a.md', 'sub/c.md']);
});

it('stamps through the stat-failing wrapper when it is not armed', function (): void {
    StatFailingStreamWrapper::register(['a.md', 'sub/b.md', 'sub/c.md']);

    // The wrapper's own positive control: without it, the raises below prove nothing
    expect(MirrorCopyFiles::stamps((new Filesystem)->allFiles(StatFailingStreamWrapper::root(), true)))
        ->toHaveCount(3);
});

it('still raises for a listed file that cannot be stat\'ed', function (): void {
    StatFailingStreamWrapper::register(['a.md', 'sub/b.md', 'sub/c.md']);
    $listing = (new Filesystem)->allFiles(StatFailingStreamWrapper::root(), true);

    // Break the file's stat while a fresh listing still holds it
    StatFailingStreamWrapper::$fileWithFailingStat = 'sub/b.md';

    MirrorCopyFiles::stamps($listing);
})->throws(RuntimeException::class, 'stat failed');

it('still raises when the directory can no longer be listed to confirm the file is gone', function (): void {
    StatFailingStreamWrapper::register(['a.md', 'sub/b.md', 'sub/c.md']);
    $listing = (new Filesystem)->allFiles(StatFailingStreamWrapper::root(), true);

    // Break the file's stat and the listing that would confirm its removal
    StatFailingStreamWrapper::$fileWithFailingStat = 'sub/b.md';
    StatFailingStreamWrapper::$listingFails = true;

    MirrorCopyFiles::stamps($listing);
})->throws(RuntimeException::class, 'stat failed');

it('copies a file whose source is present', function (): void {
    $root = mirrorCopyFilesFixture();
    $destination = $root.'/copy.md';

    expect(MirrorCopyFiles::copyOrForget($root.'/a.md', $destination))->toBeTrue()
        ->and(file_get_contents($destination))->toBe("title: a.md\n");
});

it('removes the stale copy when the source was removed before the copy', function (): void {
    $root = mirrorCopyFilesFixture();
    $destination = $root.'/copy.md';
    file_put_contents($destination, "stale\n");

    // The source was stamped, then a sibling worker removed it
    unlink($root.'/a.md');

    expect(MirrorCopyFiles::copyOrForget($root.'/a.md', $destination))->toBeFalse()
        ->and(file_exists($destination))->toBeFalse();
});

it('still raises when the copy fails and the source is still there', function (): void {
    $root = mirrorCopyFilesFixture();

    // A directory in the destination's place fails the copy with the source untouched
    mkdir($root.'/blocked');

    MirrorCopyFiles::copyOrForget($root.'/a.md', $root.'/blocked');
})->throws(RuntimeException::class, 'Could not copy');

/**
 * This process's throwaway directory for the real-filesystem cases.
 */
function mirrorCopyFilesRoot(): string
{
    return sys_get_temp_dir().'/mirror-copy-files-'.ThrowawayRoot::segment();
}

/**
 * Create a fresh directory holding `a.md`, `sub/b.md` and `sub/c.md`.
 *
 * @return string The directory's path
 */
function mirrorCopyFilesFixture(): string
{
    $files = new Filesystem;
    $root = mirrorCopyFilesRoot();

    // Start from an empty directory so a previous run's files cannot leak in
    $files->deleteDirectory($root);

    foreach (['a.md', 'sub/b.md', 'sub/c.md'] as $file) {
        $files->ensureDirectoryExists(dirname($root.'/'.$file));
        file_put_contents($root.'/'.$file, "title: {$file}\n");
    }

    return $root;
}
