<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Statamic\Stache\Traverser;
use Symfony\Component\Finder\SplFileInfo;
use Tests\Support\Doubles\StatFailingStreamWrapper;
use Tests\Support\ThrowawayRoot;
use Tests\TestCase;

/**
 * A file removed between the Stache traverser's directory listing and its mtime
 * read is skipped, and every other stat failure still raises (UAMS-Web/uams-statamic-archive#2639).
 *
 * `Traverser::traverse()` lists a store's directory and then calls `getMTime()`
 * on each file. Parallel test workers share `storage/framework/testing/content-mirror`,
 * so one worker deleting an entry it created can remove a file another worker
 * has just listed. The raise then aborts that worker's index build as a 500 in
 * whatever suite triggered it. The local `statamic-stache-traverser-vanished-file`
 * patch skips such a file.
 *
 * Each test reaches the window between listing and stat through the traverser's
 * filter, which runs per file after `sortByName()` has materialized the whole
 * listing. That makes the race deterministic: the filter removes or breaks the
 * file, then keeps it, so the very next call is the `getMTime()` under test.
 *
 * The skip must stay narrow, so the two controls use a stream wrapper rather than
 * file permissions. A permission denial is ignored for root and meaningless on
 * Windows. Either would need a platform skip, and the parallel band's skip
 * baseline counts every one. The controls nest the broken file in a
 * subdirectory, as a collection's entries nest, so they fail if the re-listing
 * reads the store's root instead of the file's own directory, or looks for the
 * file by its relative path instead of its name.
 *
 * @command  php artisan test --compact tests/Feature/Stache/TraverserVanishedFileTest.php
 */
uses(TestCase::class);

afterEach(function (): void {
    StatFailingStreamWrapper::unregister();
    File::deleteDirectory(traverserVanishedFileRoot());
});

it('skips a file removed between the listing and its mtime read', function (): void {
    $root = traverserVanishedFileFixture();

    // Remove the middle file once it has been listed, and keep it
    $paths = traverserVanishedFileTraverse(
        traverserVanishedFileStore($root),
        function (SplFileInfo $file): bool {
            // Delete the file the traversal is about to stat
            if ($file->getFilename() === 'b.md') {
                unlink($file->getPathname());
            }

            return true;
        }
    );

    expect(traverserVanishedFileNames($paths))->toBe(['a.md', 'c.md']);
});

it('skips a file removed from a subdirectory between the listing and its mtime read', function (): void {
    $root = traverserVanishedFileFixture(['a.md', 'sub/b.md', 'sub/c.md']);

    // Remove the nested file once it has been listed, and keep it
    $paths = traverserVanishedFileTraverse(
        traverserVanishedFileStore($root),
        function (SplFileInfo $file): bool {
            // Delete the file the traversal is about to stat
            if ($file->getFilename() === 'b.md') {
                unlink($file->getPathname());
            }

            return true;
        }
    );

    expect(traverserVanishedFileNames($paths))->toBe(['a.md', 'c.md']);
});

it('lists every file when nothing is removed', function (): void {
    $root = traverserVanishedFileFixture();

    $paths = traverserVanishedFileTraverse(traverserVanishedFileStore($root));

    expect(traverserVanishedFileNames($paths))->toBe(['a.md', 'b.md', 'c.md']);
});

it('lists every file through the stat-failing wrapper when it is not armed', function (): void {
    StatFailingStreamWrapper::register(['a.md', 'sub/b.md', 'sub/c.md']);

    $paths = traverserVanishedFileTraverse(traverserVanishedFileStore(StatFailingStreamWrapper::root()));

    // The wrapper's own positive control: without it, the raises below prove nothing
    expect(traverserVanishedFileNames($paths))->toBe(['a.md', 'b.md', 'c.md'])
        ->and($paths->unique()->values()->all())->toBe([StatFailingStreamWrapper::MTIME]);
});

it('still raises for a listed file that cannot be stat\'ed', function (): void {
    StatFailingStreamWrapper::register(['a.md', 'sub/b.md', 'sub/c.md']);

    // Break the middle file's stat once it has been listed, while it stays listed
    traverserVanishedFileTraverse(
        traverserVanishedFileStore(StatFailingStreamWrapper::root()),
        function (SplFileInfo $file): bool {
            // Arm the failure for the file the traversal is about to stat
            if ($file->getFilename() === 'b.md') {
                StatFailingStreamWrapper::$fileWithFailingStat = 'sub/b.md';
            }

            return true;
        }
    );
})->throws(RuntimeException::class, 'stat failed');

it('still raises when the directory can no longer be listed to confirm the file is gone', function (): void {
    StatFailingStreamWrapper::register(['a.md', 'sub/b.md', 'sub/c.md']);

    // Break the middle file's stat and the directory listing once it has been listed
    traverserVanishedFileTraverse(
        traverserVanishedFileStore(StatFailingStreamWrapper::root()),
        function (SplFileInfo $file): bool {
            // Arm both failures for the file the traversal is about to stat
            if ($file->getFilename() === 'b.md') {
                StatFailingStreamWrapper::$fileWithFailingStat = 'sub/b.md';
                StatFailingStreamWrapper::$listingFails = true;
            }

            return true;
        }
    );
})->throws(RuntimeException::class, 'stat failed');

/**
 * This process's throwaway directory for the real-filesystem cases.
 */
function traverserVanishedFileRoot(): string
{
    return storage_path('framework/testing/traverser-vanished-file/'.ThrowawayRoot::segment());
}

/**
 * Create a fresh directory holding the given files, `a.md`, `b.md` and `c.md` by default.
 *
 * @param  list<string>  $files  File paths relative to the directory
 * @return string The directory's path
 */
function traverserVanishedFileFixture(array $files = ['a.md', 'b.md', 'c.md']): string
{
    $root = traverserVanishedFileRoot();

    // Start from an empty directory so a previous run's files cannot leak in
    File::deleteDirectory($root);
    File::ensureDirectoryExists($root);

    // Write the files the traversal lists, creating any subdirectory first
    foreach ($files as $file) {
        File::ensureDirectoryExists(dirname($root.'/'.$file));
        file_put_contents($root.'/'.$file, "title: {$file}\n");
    }

    return $root;
}

/**
 * A minimal store the traverser can walk: a key and a directory.
 *
 * @param  string  $directory  Directory the store reads
 * @return object The store
 */
function traverserVanishedFileStore(string $directory): object
{
    return new class($directory)
    {
        /**
         * @param  string  $directory  Directory the store reads
         */
        public function __construct(private readonly string $directory) {}

        /**
         * The store's key, as the traverser's error message names it.
         */
        public function key(): string
        {
            return 'traverser-vanished-file';
        }

        /**
         * The directory the traverser lists.
         */
        public function directory(): string
        {
            return $this->directory;
        }
    };
}

/**
 * Run a traversal of the store, with the filter when one is given.
 *
 * `Statamic\Stache\Traverser` declares no return types, so the result is
 * narrowed here rather than at every call site.
 *
 * @param  object  $store  Store to traverse
 * @param  (Closure(SplFileInfo): bool)|null  $filter  Filter run on each listed file
 * @return Collection<string, int> Modification times keyed by path
 */
function traverserVanishedFileTraverse(object $store, ?Closure $filter = null): Collection
{
    $traverser = new Traverser;

    // Apply the filter, which returns the traverser to run
    if ($filter !== null) {
        $traverser = $traverser->filter($filter);

        // Narrowed by control flow: the untyped method hands back mixed
        if (! $traverser instanceof Traverser) {
            throw new RuntimeException('Traverser::filter() did not return a traverser');
        }
    }

    $paths = $traverser->traverse($store);

    // Narrowed by control flow for the same reason
    if (! $paths instanceof Collection) {
        throw new RuntimeException('Traverser::traverse() did not return a collection');
    }

    /** @var Collection<string, int> $paths */
    return $paths;
}

/**
 * The file names a traversal returned, in order.
 *
 * @param  Collection<string, int>  $paths  Traversal result, keyed by path
 * @return list<string> The names
 */
function traverserVanishedFileNames(Collection $paths): array
{
    $names = array_map(basename(...), array_keys($paths->all()));
    sort($names);

    return $names;
}
