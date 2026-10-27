<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use Tests\Support\StacheIsolatedTestCase;

/**
 * Teardown of the throwaway Stache root (UAMS-Web/uams-statamic-archive#1696).
 *
 * On Windows a delete is deferred while any handle on the directory is open, so
 * the entry can still be there when the recreate runs. The upstream trait used
 * to `mkdir()` it back unconditionally, raising `mkdir(): File exists` under
 * parallel load; since statamic/cms#15538 it recreates only a directory that is
 * gone, and this repository's local override is retired (UAMS-Web/uams-statamic-archive#2424).
 *
 * So the deferral is simulated instead: a `Filesystem` double whose
 * `deleteDirectory()` reports success without removing anything reproduces the
 * one state that matters — the directory outliving its own delete — on every
 * platform, deterministically.
 *
 * @command php artisan test --compact tests/Feature/StacheIsolationTeardownTest.php
 */
uses(StacheIsolatedTestCase::class);

it('recreates the throwaway Stache root when the delete has not landed', function (): void {
    $root = $this->fakeStacheDirectory;

    File::ensureDirectoryExists($root);
    File::put($root.'/stale.yaml', "title: stale\n");

    File::swap(new class extends Filesystem
    {
        /**
         * Report a successful delete without performing one, standing in for a
         * directory removal Windows has deferred.
         *
         * @param  string  $directory  The directory that would be removed.
         * @param  bool  $preserve  Whether the directory itself would be kept.
         */
        public function deleteDirectory($directory, $preserve = false): bool
        {
            return true;
        }
    });

    // Assert on raised diagnostics rather than on a thrown exception: whether a
    // PHP warning becomes an `ErrorException` depends on which error handler is
    // installed, and in the real failure that is Laravel's — active during
    // teardown, not during the test body. Catching the warning itself is the
    // handler-independent form of the same assertion.
    $raised = [];

    set_error_handler(function (int $severity, string $message) use (&$raised): bool {
        if ((error_reporting() & $severity) !== 0) {
            $raised[] = $message;
        }

        return true;
    });

    try {
        $this->deleteFakeStacheDirectory();
    } finally {
        restore_error_handler();

        // Hand the real filesystem back inside `finally` so this test's own
        // teardown still cleans up when an assertion below fails; leaving the
        // no-op double installed would carry the stale root into the next test.
        File::swap(new Filesystem);
    }

    expect($raised)->toBe([])
        ->and(is_dir($root))->toBeTrue();
});

// The upstream teardown deletes once, so a deferred removal leaves a stale fixture
// in the root. The base case's setUp clear retries, which is what keeps it out of
// the next test. A double that refuses the first delete and honors the second
// pins that: with a single attempt the root would still hold `stale.yaml`.
it('retries a delete that does not land on the first attempt', function (): void {
    $root = $this->fakeStacheDirectory;

    File::ensureDirectoryExists($root);
    File::put($root.'/stale.yaml', "title: stale\n");

    $double = new class extends Filesystem
    {
        /**
         * Number of times a delete has been requested.
         */
        public int $attempts = 0;

        /**
         * Ignore the first delete, then behave normally, standing in for a
         * removal Windows defers past its own call.
         *
         * @param  string  $directory  The directory to remove.
         * @param  bool  $preserve  Whether to keep the directory itself.
         */
        public function deleteDirectory($directory, $preserve = false): bool
        {
            $this->attempts++;

            if ($this->attempts === 1) {
                return true;
            }

            return parent::deleteDirectory($directory, $preserve);
        }
    };

    File::swap($double);

    try {
        $this->clearThrowawayDirectory($root);
    } finally {
        File::swap(new Filesystem);
    }

    expect($double->attempts)->toBeGreaterThan(1)
        ->and(File::exists($root.'/stale.yaml'))->toBeFalse();
});

it('leaves the throwaway Stache root empty and present after a delete that lands', function (): void {
    $root = $this->fakeStacheDirectory;

    File::ensureDirectoryExists($root);
    File::put($root.'/stale.yaml', "title: stale\n");

    $this->deleteFakeStacheDirectory();

    expect(is_dir($root))->toBeTrue()
        ->and(File::exists($root.'/.gitkeep'))->toBeTrue()
        ->and(File::exists($root.'/stale.yaml'))->toBeFalse();
});
