<?php

declare(strict_types=1);

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\SkippedWithMessageException;
use Statamic\Contracts\Entries\Collection as CollectionContract;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Tests\Support\ContentSubmodule;

/**
 * Regression guard for the content-submodule discriminator (UAMS-Web/uams-statamic-archive#1738).
 *
 * The guards used to skip on any `null` lookup, so a starved Stache worker or a
 * renamed handle in a fully populated checkout reported as a benign
 * "requires the content/ submodule" skip while the band still passed. Both
 * branches are pinned here: submodule absent still skips (the UAMS-Web/uams-statamic-archive#856 / UAMS-Web/uams-statamic-archive#900
 * behavior a bare worktree relies on), submodule present now fails.
 *
 * Framework-free — the discriminator is a filesystem check with the probed path
 * injectable, so both branches are driven deterministically from throwaway
 * fixtures rather than from whatever state this checkout's `content/` happens
 * to be in. Fixtures live under a per-run `sys_get_temp_dir()` path, so the file
 * is parallel-safe.
 *
 * @command  php artisan test --compact tests/Unit/ContentSubmoduleGuardTest.php
 */

/**
 * Create a throwaway collections directory, optionally holding a config file.
 *
 * @param  bool  $populated  Whether to write a collection config into it
 * @return string The fixture directory path
 */
function makeCollectionsFixture(bool $populated): string
{
    $dir = sys_get_temp_dir().'/content-submodule-'.bin2hex(random_bytes(6));
    mkdir($dir, 0o777, true);

    // A populated submodule always carries at least one collection config.
    if ($populated) {
        file_put_contents($dir.'/pages.yaml', "title: Pages\n");
    }

    return $dir;
}

/**
 * Remove a fixture directory and everything in it.
 *
 * @param  string  $dir  The fixture directory to remove
 */
function removeCollectionsFixture(string $dir): void
{
    foreach ((array) glob($dir.'/*') as $file) {
        if (is_string($file)) {
            unlink($file);
        }
    }

    rmdir($dir);
}

/**
 * Run a guard call and return whatever it threw.
 *
 * @param  callable(): mixed  $call  The guard invocation
 * @return Throwable The thrown exception
 */
function captureGuardThrowable(callable $call): Throwable
{
    try {
        $call();
    } catch (Throwable $thrown) {
        return $thrown;
    }

    Assert::fail('The guard returned instead of throwing.');
}

it('reports an empty submodule mount point as uninitialized', function (): void {
    // git leaves the mount point of an uninitialized submodule present but empty.
    $dir = makeCollectionsFixture(populated: false);

    expect(ContentSubmodule::isInitialized($dir))->toBeFalse();

    removeCollectionsFixture($dir);
});

it('reports an absent directory as uninitialized', function (): void {
    expect(ContentSubmodule::isInitialized(sys_get_temp_dir().'/content-submodule-absent-'.bin2hex(random_bytes(6))))
        ->toBeFalse();
});

it('reports a directory holding collection configs as initialized', function (): void {
    $dir = makeCollectionsFixture(populated: true);

    expect(ContentSubmodule::isInitialized($dir))->toBeTrue();

    removeCollectionsFixture($dir);
});

it('skips a null entry lookup when the submodule is absent', function (): void {
    $dir = makeCollectionsFixture(populated: false);

    $thrown = captureGuardThrowable(
        static fn (): EntryContract => ContentSubmodule::requireEntry(null, 'a knowledge_bases root entry', $dir)
    );

    // Exact class, not instanceof: SkippedWithMessageException extends
    // AssertionFailedError, so a looser check would pass for either branch.
    expect($thrown::class)->toBe(SkippedWithMessageException::class);
    expect($thrown->getMessage())->toContain('Requires the content/ submodule (a knowledge_bases root entry).');

    removeCollectionsFixture($dir);
});

it('fails a null entry lookup when the submodule is present', function (): void {
    $dir = makeCollectionsFixture(populated: true);

    $thrown = captureGuardThrowable(
        static fn (): EntryContract => ContentSubmodule::requireEntry(null, 'a knowledge_bases root entry', $dir)
    );

    expect($thrown::class)->toBe(AssertionFailedError::class);
    // Naming what did not resolve is the point — a bare failure says nothing.
    expect($thrown->getMessage())->toContain('a knowledge_bases root entry');
    expect($thrown->getMessage())->toContain('not an absent submodule');

    removeCollectionsFixture($dir);
});

it('skips a null collection lookup when the submodule is absent', function (): void {
    $dir = makeCollectionsFixture(populated: false);

    $thrown = captureGuardThrowable(
        static fn (): CollectionContract => ContentSubmodule::requireCollection(null, 'people', $dir)
    );

    expect($thrown::class)->toBe(SkippedWithMessageException::class);
    expect($thrown->getMessage())->toBe('Requires the content/ submodule (people collection).');

    removeCollectionsFixture($dir);
});

it('fails a null collection lookup when the submodule is present', function (): void {
    $dir = makeCollectionsFixture(populated: true);

    $thrown = captureGuardThrowable(
        static fn (): CollectionContract => ContentSubmodule::requireCollection(null, 'people', $dir)
    );

    expect($thrown::class)->toBe(AssertionFailedError::class);
    expect($thrown->getMessage())->toContain('people collection');

    removeCollectionsFixture($dir);
});

it('returns a resolved entry untouched in either environment', function (): void {
    $entry = Mockery::mock(EntryContract::class);
    $absent = makeCollectionsFixture(populated: false);
    $present = makeCollectionsFixture(populated: true);

    expect(ContentSubmodule::requireEntry($entry, 'an entry', $absent))->toBe($entry);
    expect(ContentSubmodule::requireEntry($entry, 'an entry', $present))->toBe($entry);

    removeCollectionsFixture($absent);
    removeCollectionsFixture($present);
});

it('returns a resolved collection untouched in either environment', function (): void {
    $collection = Mockery::mock(CollectionContract::class);
    $absent = makeCollectionsFixture(populated: false);
    $present = makeCollectionsFixture(populated: true);

    expect(ContentSubmodule::requireCollection($collection, 'people', $absent))->toBe($collection);
    expect(ContentSubmodule::requireCollection($collection, 'people', $present))->toBe($collection);

    removeCollectionsFixture($absent);
    removeCollectionsFixture($present);
});

it('probes this checkout content/collections by default', function (): void {
    // The default path is what every real guard call resolves against; a wrong
    // one would make every guard take the same branch forever.
    expect(ContentSubmodule::collectionsPath())
        ->toBe(dirname(__DIR__, 2).'/content/collections');
});
