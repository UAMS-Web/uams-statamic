<?php

declare(strict_types=1);

use App\Cache\ScopeAliasCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Coverage for the shared cache directory `ScopeAliasCache` creates (UAMS-Web/uams-statamic-archive#1976).
 *
 * The defect was a check-then-create race: `File::ensureDirectoryExists()` tests
 * `isDirectory()` and then calls Laravel's unguarded `mkdir()`, so a process
 * that loses the race to a sibling takes a `mkdir(): File exists` warning — an
 * `ErrorException` under the test error handler. The directory is shared by
 * every process (no `TEST_TOKEN` segment) and sits on the boot path, which is
 * what makes the race reachable.
 *
 * The race is simulated rather than run concurrently: the losing side is
 * precisely "isDirectory() said absent, the filesystem says present by the time
 * mkdir runs", and a facade partial-mock reproduces that state deterministically.
 * A genuinely concurrent test would be slow and flaky and would assert less.
 *
 * @command php artisan test --compact tests/Feature/Cache/ScopeAliasCacheTest.php
 */
const SCOPE_ALIAS_CACHE_DIR = 'framework/cache/data/entries_by_schema_type';

/**
 * Invoke the private creation routine directly.
 *
 * Reached in production only when Laravel's cache is unbound — `put()`
 * short-circuits to `Cache::put()` otherwise — which is awkward to arrange
 * inside a booted test. Reflection targets the behavior under test without
 * pretending the cache is unavailable.
 */
function invokeEnsureDirectory(ScopeAliasCache $cache): void
{
    $method = new ReflectionMethod($cache, 'ensureDirectory');
    $method->invoke($cache);
}

it('creates the shared cache directory when it is absent', function (): void {
    $dir = storage_path(SCOPE_ALIAS_CACHE_DIR);
    File::deleteDirectory($dir);

    expect(File::isDirectory($dir))->toBeFalse();

    invokeEnsureDirectory(new ScopeAliasCache);

    expect(File::isDirectory($dir))->toBeTrue();

    // 0755, less the process umask. Windows has no POSIX mode to set, and PHP
    // reports every directory there as 0777, so the mode is checked elsewhere.
    if (PHP_OS_FAMILY !== 'Windows') {
        expect(fileperms($dir) & 0777)->toBe(0755 & ~umask());
    }
});

it('does not warn when a sibling process created the directory first', function (): void {
    // ---
    // The regression. `isDirectory()` is forced to report the directory absent
    // while it genuinely exists — exactly the state the loser of the race is in
    // between its own check and its own mkdir.
    //
    // Under the old `ensureDirectoryExists()` implementation this reached an
    // unguarded `mkdir()` on an existing directory, raising `mkdir(): File
    // exists`, which the test environment's error handler promotes to an
    // ErrorException — so this test would fail rather than merely warn.
    // ---
    $dir = storage_path(SCOPE_ALIAS_CACHE_DIR);
    File::ensureDirectoryExists($dir);

    expect(File::isDirectory($dir))->toBeTrue();

    File::partialMock()
        ->shouldReceive('isDirectory')
        ->with($dir)
        ->andReturnFalse();

    invokeEnsureDirectory(new ScopeAliasCache);

    // Positive evidence rather than merely "nothing threw": an absent directory
    // here would mean the routine had destroyed or moved what it found.
    expect(is_dir($dir))->toBeTrue();
});

it('leaves an existing directory alone on the common path', function (): void {
    // The overwhelmingly common case — the directory exists and the routine
    // should do no filesystem work beyond the check. Pinned so a future fix for
    // the race cannot start unconditionally calling mkdir on every boot.
    $dir = storage_path(SCOPE_ALIAS_CACHE_DIR);
    File::ensureDirectoryExists($dir);

    $filesystem = File::partialMock();
    $filesystem->shouldReceive('isDirectory')->with($dir)->once()->andReturnTrue();
    $filesystem->shouldNotReceive('makeDirectory');

    invokeEnsureDirectory(new ScopeAliasCache);
});

/**
 * A key no other test or process uses, so a file written under the shared directory
 * cannot collide with anyone else's.
 */
function scopeAliasCacheKey(): string
{
    return 'scope-alias-cache-test-'.uniqid('', true);
}

/**
 * The file `ScopeAliasCache` keeps a key in when Laravel's cache is not bound.
 */
function scopeAliasCachePath(string $key): string
{
    return storage_path(SCOPE_ALIAS_CACHE_DIR).DIRECTORY_SEPARATOR.sha1($key);
}

/**
 * Remove the `cache` binding, as before the cache service provider has registered it.
 */
function unbindLaravelCache(): void
{
    $app = app();
    unset($app['cache']);

    expect($app->bound('cache'))->toBeFalse();
}

describe('with Laravel\'s cache bound', function (): void {
    beforeEach(function (): void {
        // The array store, so nothing reaches the cache files other processes share
        Cache::setDefaultDriver('array');
    });

    it('remembers through Laravel\'s cache and writes no file', function (): void {
        $key = scopeAliasCacheKey();
        $cache = new ScopeAliasCache;
        $calls = 0;

        $first = $cache->rememberForever($key, function () use (&$calls): array {
            $calls++;

            return ['Thing', 'CreativeWork'];
        });
        $second = $cache->rememberForever($key, fn (): array => ['ignored']);

        expect($first)->toBe(['Thing', 'CreativeWork'])
            ->and($second)->toBe(['Thing', 'CreativeWork'])
            ->and($calls)->toBe(1)
            ->and(Cache::get($key))->toBe(['Thing', 'CreativeWork'])
            ->and(File::exists(scopeAliasCachePath($key)))->toBeFalse();
    });

    it('stores a put for ten years', function (): void {
        $this->freezeTime();
        $key = scopeAliasCacheKey();

        Cache::shouldReceive('put')
            ->once()
            ->withArgs(fn (string $putKey, array $value, mixed $ttl): bool => $putKey === $key
                && $value === ['Person']
                && $ttl instanceof DateTimeInterface
                && $ttl->getTimestamp() === now()->addYears(10)->getTimestamp());

        (new ScopeAliasCache)->put($key, ['Person']);
    });

    it('puts and forgets through Laravel\'s cache', function (): void {
        $key = scopeAliasCacheKey();
        $cache = new ScopeAliasCache;

        $cache->put($key, ['Person']);

        expect(Cache::get($key))->toBe(['Person'])
            ->and(File::exists(scopeAliasCachePath($key)))->toBeFalse();

        $cache->forget($key);

        expect(Cache::has($key))->toBeFalse();
    });
});

describe('without Laravel\'s cache bound', function (): void {
    it('computes once, writes the list as JSON, and reads it back without computing again', function (): void {
        $key = scopeAliasCacheKey();
        $path = scopeAliasCachePath($key);
        $cache = new ScopeAliasCache;
        unbindLaravelCache();

        try {
            $first = $cache->rememberForever($key, fn (): array => ['Thing', 'Event']);
            $second = $cache->rememberForever($key, fn (): array => ['recomputed']);

            expect($first)->toBe(['Thing', 'Event'])
                ->and($second)->toBe(['Thing', 'Event'])
                ->and(File::get($path))->toBe('["Thing","Event"]');
        } finally {
            File::delete($path);
        }
    });

    it('recomputes and rewrites a file that does not hold a JSON list', function (string $contents): void {
        $key = scopeAliasCacheKey();
        $path = scopeAliasCachePath($key);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);
        $cache = new ScopeAliasCache;
        unbindLaravelCache();

        try {
            expect($cache->rememberForever($key, fn (): array => ['Place']))->toBe(['Place'])
                ->and(File::get($path))->toBe('["Place"]');
        } finally {
            File::delete($path);
        }
    })->with([
        'not JSON' => ['not json'],
        'a JSON string' => ['"Thing"'],
    ]);

    it('reads a JSON object file as an array', function (): void {
        $key = scopeAliasCacheKey();
        $path = scopeAliasCachePath($key);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, '{"0":"Thing","1":"Event"}');
        $cache = new ScopeAliasCache;
        unbindLaravelCache();

        try {
            expect($cache->rememberForever($key, fn (): array => ['recomputed']))->toBe(['Thing', 'Event']);
        } finally {
            File::delete($path);
        }
    });

    it('writes an empty list when the value cannot be encoded', function (): void {
        $key = scopeAliasCacheKey();
        $path = scopeAliasCachePath($key);
        $cache = new ScopeAliasCache;
        unbindLaravelCache();

        try {
            // Invalid UTF-8 makes json_encode() fail
            $cache->put($key, ["\xB1\x31"]);

            expect(File::get($path))->toBe('[]');
        } finally {
            File::delete($path);
        }
    });

    it('forgets by deleting the file, and does nothing for a key with no file', function (): void {
        $key = scopeAliasCacheKey();
        $path = scopeAliasCachePath($key);
        $cache = new ScopeAliasCache;
        unbindLaravelCache();

        try {
            $cache->put($key, ['Thing']);

            expect(File::exists($path))->toBeTrue();

            $cache->forget($key);
            $cache->forget(scopeAliasCacheKey());

            expect(File::exists($path))->toBeFalse();
        } finally {
            File::delete($path);
        }
    });
});
