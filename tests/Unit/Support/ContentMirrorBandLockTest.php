<?php

declare(strict_types=1);

use App\Support\ContentMirrorBandLock;
use Illuminate\Filesystem\Filesystem;

/**
 * What the content mirror band lock does when its file cannot be opened (UAMS-Web/uams-statamic-archive#2773).
 *
 * `hold()` used to return with no lock held when `fopen()` failed, so a band
 * that could not protect itself ran unprotected and a concurrent seed rewrote
 * the mirror beneath it — the UAMS-Web/uams-statamic-archive#2762 tear, restored silently. Both paths now
 * share one open, so they must fail the same way.
 *
 * Exercised on a throwaway mirror path rather than the real one. A process
 * that booted the app earlier already holds the real shared lock, so its handle
 * is parked for each test and put back afterwards, unreleased: releasing it
 * would open a window in which a seed could start beneath this worker's band.
 *
 * The open failure is a lock file the current user cannot write, the shared-
 * machine leftover the ticket names. Where that cannot be arranged (running as
 * root), the open-failure tests skip rather than pass vacuously.
 *
 * @command  php artisan test --compact tests/Unit/Support/ContentMirrorBandLockTest.php
 */
$mirrorRoot = null;
$parkedHandle = null;

beforeEach(function () use (&$mirrorRoot, &$parkedHandle): void {
    $mirrorRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'content-mirror-band-lock-test-'.uniqid('', true);
    (new Filesystem)->ensureDirectoryExists($mirrorRoot);

    // Park this process's real shared lock, if it holds one, so hold() runs its first-call path
    $parkedHandle = bandLockHeldHandle();
    bandLockSetHeldHandle(null);
});

afterEach(function () use (&$mirrorRoot, &$parkedHandle): void {
    // Drop any lock a test took on the throwaway mirror, then restore the real one
    ContentMirrorBandLock::release();
    bandLockSetHeldHandle($parkedHandle);

    if (! \is_string($mirrorRoot)) {
        return;
    }

    // Make the planted lock file deletable again before removing the directory
    $lockFile = ContentMirrorBandLock::path(bandLockMirror($mirrorRoot));

    if (file_exists($lockFile)) {
        chmod($lockFile, 0644);
    }

    (new Filesystem)->deleteDirectory($mirrorRoot);
});

/**
 * The throwaway mirror path whose band lock file the tests open.
 *
 * @param  string  $mirrorRoot  The per-test temporary directory
 */
function bandLockMirror(string $mirrorRoot): string
{
    return $mirrorRoot.DIRECTORY_SEPARATOR.'content-mirror';
}

/**
 * The handle this process holds its shared band lock on, or `null`.
 *
 * @return resource|null
 */
function bandLockHeldHandle()
{
    /** @var resource|null $handle */
    $handle = (new ReflectionProperty(ContentMirrorBandLock::class, 'held'))->getValue();

    return $handle;
}

/**
 * Replace the handle this process records as holding the shared band lock.
 *
 * @param  resource|null  $handle  The handle to record, or `null` for none
 */
function bandLockSetHeldHandle($handle): void
{
    (new ReflectionProperty(ContentMirrorBandLock::class, 'held'))->setValue(null, $handle);
}

/**
 * Plant a band lock file the current user cannot open for writing.
 *
 * The caller skips when this returns false, as it does for root, because an
 * open that succeeds would exercise nothing.
 *
 * @param  string  $mirror  The mirror whose lock file to plant
 * @return bool Whether the planted file is in fact read-only for this user
 */
function bandLockPlantReadOnly(string $mirror): bool
{
    $lockFile = ContentMirrorBandLock::path($mirror);

    touch($lockFile);
    chmod($lockFile, 0444);
    clearstatcache(true, $lockFile);

    return ! is_writable($lockFile);
}

it('fails loudly when a test process cannot open the band lock file', function () use (&$mirrorRoot): void {
    /** @var string $mirrorRoot */
    $mirror = bandLockMirror($mirrorRoot);

    if (! bandLockPlantReadOnly($mirror)) {
        $this->markTestSkipped('The lock file stays writable for this user, so an open cannot be made to fail.');
    }

    expect(fn () => ContentMirrorBandLock::hold($mirror))
        ->toThrow(RuntimeException::class, 'Could not open the content mirror band lock at '.ContentMirrorBandLock::path($mirror).': ')
        // The reason is the open's own warning, not the fallback, so a reader can act on it
        ->toThrow(RuntimeException::class, 'Failed to open stream');

    // Nothing is recorded as held, so a later call retries rather than trusting a lock it never took
    expect(bandLockHeldHandle())->toBeNull();
});

it('fails the same way in hold() and tryExclusive() when the lock file cannot be opened', function () use (&$mirrorRoot): void {
    /** @var string $mirrorRoot */
    $mirror = bandLockMirror($mirrorRoot);

    if (! bandLockPlantReadOnly($mirror)) {
        $this->markTestSkipped('The lock file stays writable for this user, so an open cannot be made to fail.');
    }

    $holdMessage = null;
    $exclusiveMessage = null;

    try {
        ContentMirrorBandLock::hold($mirror);
    } catch (RuntimeException $exception) {
        $holdMessage = $exception->getMessage();
    }

    try {
        ContentMirrorBandLock::tryExclusive($mirror);
    } catch (RuntimeException $exception) {
        $exclusiveMessage = $exception->getMessage();
    }

    expect($holdMessage)->not->toBeNull()
        ->and($holdMessage)->toBe($exclusiveMessage);
});

it('takes the shared lock on a writable lock file, so a seed cannot take the exclusive one', function () use (&$mirrorRoot): void {
    /** @var string $mirrorRoot */
    $mirror = bandLockMirror($mirrorRoot);

    ContentMirrorBandLock::hold($mirror);

    expect(bandLockHeldHandle())->toBeResource()
        // A seed alongside this band is refused rather than rewriting the mirror
        ->and(ContentMirrorBandLock::tryExclusive($mirror))->toBeNull();
});

it('lets a seed take the exclusive lock once no band holds the shared one', function () use (&$mirrorRoot): void {
    /** @var string $mirrorRoot */
    $mirror = bandLockMirror($mirrorRoot);

    $handle = ContentMirrorBandLock::tryExclusive($mirror);

    expect($handle)->toBeResource();

    /** @var resource $handle */
    ContentMirrorBandLock::releaseExclusive($handle);
});
