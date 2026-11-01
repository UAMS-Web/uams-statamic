<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Config;
use Tests\CreatesApplication;
use Tests\TestCase;

uses(TestCase::class);

// These call the real seeding method, which increments the process-global
// `stacheSnapshotCopyFailures` counter — and TestBootstrapCostTest asserts on that
// counter. paratest puts several files in one worker, so leaking three forced
// failures per test (three attempts each) made that guard read 9 against a cap of
// 3 and fail. Snapshot and restore it so these tests observe the real code without
// mutating shared state a sibling test depends on.
beforeEach(function (): void {
    $property = new ReflectionProperty(TestCase::class, 'stacheSnapshotCopyFailures');
    $this->copyFailuresBefore = $property->getValue();
});

afterEach(function (): void {
    $property = new ReflectionProperty(TestCase::class, 'stacheSnapshotCopyFailures');
    $property->setValue(null, $this->copyFailuresBefore);
});

/**
 * Guards the snapshot-copy verification added in UAMS-Web/uams-statamic-archive#1740.
 *
 * `Filesystem::copyDirectory()` aborts at the *first* failed `copy()`, returns
 * `false`, and leaves behind whatever it had already written. The seeding path
 * used to discard that return value and mark the worker seeded regardless, so a
 * worker whose copy died two files in ran the entire band against a near-empty
 * Stache — and because the content guards report an unresolvable entry as a
 * benign "requires the content/ submodule" skip, the band still exited 0.
 * Measured on one band: no worker of twelve received the complete 4,586-file
 * snapshot, and one received two files.
 *
 * These exercise {@see CreatesApplication::seedStacheFromSnapshot()}
 * directly rather than through `createApplication()`, because the seed/warm
 * decision is gated by a once-per-process static that has already fired by the
 * time any test body runs. Both redirect the app's storage and cache paths at a
 * scratch directory, so the shared `storage/framework/stache-seed` the parallel
 * suite depends on is never touched.
 *
 * @covers \Tests\CreatesApplication
 *
 * @command  php artisan test --compact tests/Feature/StacheSnapshotCopyVerificationTest.php
 */

/**
 * Point the application at throwaway storage and cache directories, and return
 * the seed directory to populate.
 *
 * @return array{0: string, 1: string} The seed directory and the cache directory
 */
function stacheSnapshotScratchPaths(Application $app, string $label): array
{
    $root = sys_get_temp_dir().'/stache-snapshot-'.$label.'-'.getmypid();

    $app->useStoragePath($root.'/storage');
    $cacheDirectory = $root.'/cache';
    Config::set('cache.stores.file.path', $cacheDirectory);

    return [$root.'/storage/framework/stache-seed', $cacheDirectory];
}

/**
 * Invoke the private seeding method on the current test case.
 */
function seedStacheFromSnapshotVia(TestCase $testCase, Application $app): bool
{
    // Global class referenced directly: a `use ReflectionMethod;` in a Pest file
    // (global namespace) is a no-op that raises a PHP warning, which the bands'
    // --display-all-issues promotes to an error and aborts the whole run.
    $method = new ReflectionMethod($testCase, 'seedStacheFromSnapshot');

    return (bool) $method->invoke($testCase, $app);
}

/**
 * Narrow the test case's `$app`, which PHPStan sees as `mixed` inside a Pest
 * closure (this project does not load Pest's own PHPStan extension).
 */
function stacheSnapshotApp(mixed $app): Application
{
    // Narrowing by control flow rather than `assert()`, which the analyser only
    // takes on trust and which `zend.assertions=-1` compiles away entirely (UAMS-Web/uams-statamic-archive#1733).
    if (! $app instanceof Application) {
        throw new RuntimeException(sprintf(
            'Expected the test case to expose an Application, got %s.',
            get_debug_type($app),
        ));
    }

    return $app;
}

/**
 * The happy path still copies, and still reports success — a fix that made the
 * method conservative enough to always warm would pass the failure test below
 * while silently handing back the ~168s-per-worker cost UAMS-Web/uams-statamic-archive#1581 removed.
 */
it('reports success and copies every file when the snapshot is intact', function (): void {
    $files = new Filesystem;
    $app = stacheSnapshotApp($this->app);
    [$seedDirectory, $cacheDirectory] = stacheSnapshotScratchPaths($app, 'intact');

    // A snapshot shaped like the real one: nested dirs plus a dotfile, since
    // allFiles() ignores dotfiles and the counts must agree on both sides.
    $files->ensureDirectoryExists($seedDirectory.'/stache/items/pages');
    $files->put($seedDirectory.'/stache/keys', 'keys');
    $files->put($seedDirectory.'/stache/items/pages/home', 'home');
    $files->put($seedDirectory.'/.gitignore', '*');

    expect(seedStacheFromSnapshotVia($this, $app))->toBeTrue();

    // Every non-dot file arrived, and the nested structure is preserved.
    expect($files->allFiles($cacheDirectory))->toHaveCount(2);
    expect($files->get($cacheDirectory.'/stache/items/pages/home'))->toBe('home');

    $files->deleteDirectory(dirname($seedDirectory, 3));
});

/**
 * The regression: `copyDirectory()` aborts mid-way and returns false, having
 * already written part of the tree. The method must report failure — so the caller
 * warms — and must not leave that partial cache behind, because a truncated index
 * that is *present* reads as authoritative and is exactly what starved the worker.
 */
it('reports failure and leaves no partial cache when a file cannot be copied', function (): void {
    $files = new Filesystem;
    $app = stacheSnapshotApp($this->app);
    [$seedDirectory, $cacheDirectory] = stacheSnapshotScratchPaths($app, 'partial');

    $files->ensureDirectoryExists($seedDirectory.'/stache/items');
    $files->put($seedDirectory.'/stache/keys', 'keys');

    foreach (range(1, 8) as $n) {
        $files->put($seedDirectory."/stache/items/entry-{$n}", "entry-{$n}");
    }

    // The abort is injected rather than provoked with `chmod 0000`: that trick
    // does nothing on Windows (PHP maps only the write bit to the read-only
    // attribute) or as root, so the test would have had to skip there — a
    // platform-conditional skip in a suite whose skips are meaningful signal.
    //
    // This writes a few files and *then* fails, which is the field shape: a
    // present-but-truncated cache the worker would otherwise trust.
    $app->instance(Filesystem::class, new class extends Filesystem
    {
        public function copyDirectory($directory, $destination, $options = null): bool
        {
            $this->ensureDirectoryExists($destination.'/stache/items');
            $this->put($destination.'/stache/keys', 'keys');
            $this->put($destination.'/stache/items/entry-1', 'entry-1');

            return false;
        }
    });

    expect(seedStacheFromSnapshotVia($this, $app))->toBeFalse();

    // The debris is cleared: a starved worker must not inherit a partial index.
    expect($files->allFiles($cacheDirectory))->toBe([]);

    $files->deleteDirectory(dirname($seedDirectory, 3));
});

/**
 * The count check earns its keep on its own: a copy that *reports success* while
 * landing fewer files than the source must still be rejected. That is the exact
 * field symptom — workers finished seeding and carried on with a fraction of the
 * snapshot — and the return value alone does not catch it.
 *
 * The real `Filesystem` cannot be made to return `true` from a short copy, so the
 * short copy is injected: a stub that reports success having written nothing.
 * Without the count check this test goes green, which is how the gap was found.
 */
it('reports failure when the copy reports success but lands fewer files', function (): void {
    $files = new Filesystem;
    $app = stacheSnapshotApp($this->app);
    [$seedDirectory, $cacheDirectory] = stacheSnapshotScratchPaths($app, 'truncated');

    $files->ensureDirectoryExists($seedDirectory.'/stache');
    $files->put($seedDirectory.'/stache/keys', 'keys');
    $files->put($seedDirectory.'/stache/items', 'items');

    // Claims the copy worked, writes nothing — the shape the return value misses.
    $app->instance(Filesystem::class, new class extends Filesystem
    {
        public function copyDirectory($directory, $destination, $options = null): bool
        {
            return true;
        }
    });

    expect(seedStacheFromSnapshotVia($this, $app))->toBeFalse();
    expect($files->allFiles($cacheDirectory))->toBe([]);

    $files->deleteDirectory(dirname($seedDirectory, 3));
});

/**
 * Each attempt restarts from an empty destination. Without that, debris from a
 * previous partial attempt (or a stale worker cache) can pad the destination up
 * to the expected count and satisfy the check with files that were never copied
 * — turning the verification into exactly the false green it exists to prevent.
 */
it('clears a stale destination rather than counting its leftovers', function (): void {
    $files = new Filesystem;
    $app = stacheSnapshotApp($this->app);
    [$seedDirectory, $cacheDirectory] = stacheSnapshotScratchPaths($app, 'stale');

    $files->ensureDirectoryExists($seedDirectory.'/stache');
    $files->put($seedDirectory.'/stache/keys', 'keys');
    $files->put($seedDirectory.'/stache/items', 'items');

    // Pre-existing junk that matches the source count but shares none of its
    // content — the shape that would pass a naive count-only check.
    $files->ensureDirectoryExists($cacheDirectory);
    $files->put($cacheDirectory.'/leftover-one', 'stale');
    $files->put($cacheDirectory.'/leftover-two', 'stale');

    expect(seedStacheFromSnapshotVia($this, $app))->toBeTrue();

    // The leftovers are gone and only the snapshot's own files remain.
    expect($files->exists($cacheDirectory.'/leftover-one'))->toBeFalse();
    expect($files->exists($cacheDirectory.'/leftover-two'))->toBeFalse();
    expect($files->allFiles($cacheDirectory))->toHaveCount(2);
    expect($files->get($cacheDirectory.'/stache/keys'))->toBe('keys');

    $files->deleteDirectory(dirname($seedDirectory, 3));
});

/**
 * Clearing the cache directory keeps a `.gitignore` that is in it. The real test
 * cache root has carried none since UAMS-Web/uams-statamic#408; this one is planted.
 */
it('preserves the tracked .gitignore when it clears the cache directory', function (): void {
    $files = new Filesystem;
    $app = stacheSnapshotApp($this->app);
    [$seedDirectory, $cacheDirectory] = stacheSnapshotScratchPaths($app, 'gitignore');

    $files->ensureDirectoryExists($seedDirectory.'/stache');
    $files->put($seedDirectory.'/stache/keys', 'keys');

    $files->ensureDirectoryExists($cacheDirectory);
    $files->put($cacheDirectory.'/.gitignore', "*\n!.gitignore\n");

    // Force the give-up path, which is where the final clear happens.
    $app->instance(Filesystem::class, new class extends Filesystem
    {
        public function copyDirectory($directory, $destination, $options = null): bool
        {
            return false;
        }
    });

    expect(seedStacheFromSnapshotVia($this, $app))->toBeFalse();

    expect($files->exists($cacheDirectory.'/.gitignore'))->toBeTrue();
    expect($files->get($cacheDirectory.'/.gitignore'))->toBe("*\n!.gitignore\n");

    $files->deleteDirectory(dirname($seedDirectory, 3));
});
