<?php

declare(strict_types=1);

use App\Support\ContentMirrorBandLock;
use Symfony\Component\Process\Process;
use Tests\Support\ThrowawayRoot;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Regression guard for the band-lock release check (UAMS-Web/uams-statamic-archive#3018). Runs
 * `scripts/check-band-lock-released.php` against a throwaway lock file and asserts
 * it fails while a process that outlived its parent still holds the lock through
 * an inherited descriptor, and passes once that process exits.
 *
 * The leak this reproduces is the one measured on UAMS-Web/uams-statamic-archive#3003: a process takes the
 * shared lock, starts a child, and exits, and the child keeps the lock because
 * `proc_open` children inherit open descriptors. The child here waits for a stop
 * file rather than being killed, so no platform-specific signal is needed.
 *
 * That leak does not exist on Windows, so the orphan case asserts the opposite
 * there (UAMS-Web/uams-statamic-archive#3035). PHP's `flock()` is `LockFileEx` on Windows, and the lock is
 * dropped when the process that took it exits, whatever its children inherit:
 * measured on Windows 11 build 26200.9711 with PHP 8.4.20 under Git Bash, the
 * guard acquired the lock with the orphaned child alive in 3 of 3 runs, and a
 * child that took its own lock made it exit 1 in 3 of 3. Both platforms run an
 * assertion, so the skip baseline is unchanged.
 *
 * Driven as a subprocess, like `SkipBaselineGuardTest`, so the exit code and the
 * output that ci.yml and local-ci.mjs read are what is pinned. Each case uses its
 * own lock file under `storage/framework/testing`, so it is parallel-safe and
 * never touches the real band lock.
 *
 * @command  php artisan test --compact tests/Feature/BandLockReleasedGuardTest.php
 */

/**
 * Run the guard against a lock file.
 *
 * @param  string|null  $path  The lock file, or null for the script's default
 * @return Process The finished guard process
 */
function runBandLockGuard(?string $path): Process
{
    // Build the command, passing the path only when one was given
    $command = [PHP_BINARY, base_path('scripts/check-band-lock-released.php')];
    if ($path !== null) {
        $command[] = '--path='.$path;
    }

    $process = new Process($command, base_path());
    $process->run();

    return $process;
}

/**
 * Leave behind a process that may hold a shared lock on a file through an
 * inherited descriptor: a short-lived parent takes the lock, starts a child that
 * appends to the heartbeat file until the stop file appears, and exits.
 *
 * The parent is started with its standard streams on files rather than pipes, and
 * the part of that which matters is that nothing reads the parent's output to its
 * end. The child is started with `[]` descriptors, so it inherits the parent's
 * standard streams. When those are a pipe, the child holds the pipe's write end,
 * and a reader waiting for end-of-file waits until the child exits, not the
 * parent. UAMS-Web/uams-statamic-archive#3035 read that as the child stopping with its parent on Windows; it was
 * the launcher blocking until the child ran out its own thirty seconds, so by the
 * time the case looked, the orphan had finished. Measured for UAMS-Web/uams-statamic-archive#3266 on Windows 11
 * build 26200 with PHP 8.4.20 and Symfony Process 8.1.9, 3 of 3 runs per shape:
 *
 * - Streams on files, as here: the launch returns in about 0.2s, the child is
 *   alive after the parent exits, and it exits with code 0, its shutdown handler
 *   run, when the stop file appears.
 * - Streams on pipes read to end-of-file: the launch blocks until the child's own
 *   deadline, and the child then exits with code 0 by that deadline. Nothing
 *   terminates it.
 * - Streams on pipes that are not read, with the parent polled until it exits:
 *   returns in about 0.2s, and the child survives as with files.
 * - Through Symfony `Process`, including the pre-#3035 shape run inside Pest:
 *   the launch returns in under half a second and the child survives. On Windows Symfony
 *   sends a process's output to temporary files rather than pipes, so it does not
 *   wait on the inherited handle. The UAMS-Web/uams-statamic-archive#3035 observation of this arm did not
 *   reproduce.
 *
 * So no launch shape stops the child, and the two arms UAMS-Web/uams-statamic-archive#3035 reported do not
 * share a cause. A job object is ruled out, since no shape terminates the child,
 * and so is a child blocking on a write, since the child writes nothing to the
 * inherited stream. The launch may be any shape that does not wait for the
 * parent's output to close; files are kept because they make that true without
 * depending on how the output is read.
 *
 * The child inherits every descriptor this test process has open, which includes
 * the real band lock it took at boot, so that lock is released around the spawn.
 * Otherwise a case that died before `afterEach` would leave the child holding the
 * real lock, which is the leak this guard reports. The child also gives up after
 * thirty seconds, so a missing stop file cannot keep it alive.
 *
 * @param  string  $lock  The lock file the parent locks
 * @param  string  $stop  The file whose creation lets the child exit
 * @param  string  $beat  The file the child appends to while it runs
 * @return int The child's process id
 */
function orphanBandLockHolder(string $lock, string $stop, string $beat): int
{
    // The parent locks the file, starts the beating child, prints its pid and exits
    $parent = <<<'PHP'
        $handle = fopen($argv[1], 'c');
        flock($handle, LOCK_SH);
        $child = proc_open([PHP_BINARY, '-r', '$end = time() + 30; while (! file_exists($argv[1]) && time() < $end) { file_put_contents($argv[2], "x", FILE_APPEND); usleep(50000); }', '--', $argv[2], $argv[3]], [], $pipes);
        echo proc_get_status($child)['pid'];
        PHP;

    // The parent's output goes to a file, so nothing waits on the stream the child inherits
    $output = $lock.'.out';
    $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';

    // Keep the real band lock out of the child, and take it back once it is started
    ContentMirrorBandLock::release();

    try {
        $process = proc_open(
            [PHP_BINARY, '-r', $parent, '--', $lock, $stop, $beat],
            [0 => ['file', $null, 'r'], 1 => ['file', $output, 'w'], 2 => ['file', $output, 'a']],
            $pipes,
        );
        if ($process === false) {
            throw new RuntimeException('The band lock orphan parent could not be started.');
        }

        expect(proc_close($process))->toBe(0);
    } finally {
        ContentMirrorBandLock::hold();
    }

    return (int) file_get_contents($output);
}

/**
 * Wait until the heartbeat file has grown past a count, for at most five seconds.
 *
 * @param  string  $beat  The heartbeat file
 * @param  int  $count  The length it must exceed
 * @return int The length it reached, or the last length read when it did not grow
 */
function waitForBandLockHeartbeat(string $beat, int $count): int
{
    $deadline = microtime(true) + 5;

    do {
        // Clear the stat cache so a growing file is not read at its old size
        clearstatcache(true, $beat);
        $length = is_file($beat) ? (int) filesize($beat) : 0;
        if ($length > $count) {
            return $length;
        }

        usleep(50000);
    } while (microtime(true) < $deadline);

    return $length;
}

/**
 * Wait until an exclusive lock on the file can be taken, for at most ten seconds.
 *
 * @param  string  $lock  The lock file
 * @return bool Whether the lock became free in time
 */
function waitForBandLockRelease(string $lock): bool
{
    // A file that was never created is held by nothing
    if (! is_file($lock)) {
        return true;
    }

    $deadline = microtime(true) + 10;

    while (microtime(true) < $deadline) {
        // Try the same lock the guard takes, and release it at once
        $handle = fopen($lock, 'r');
        if ($handle !== false && flock($handle, LOCK_EX | LOCK_NB)) {
            flock($handle, LOCK_UN);
            fclose($handle);

            return true;
        }

        if ($handle !== false) {
            fclose($handle);
        }

        usleep(50000);
    }

    return false;
}

beforeEach(function () {
    // Give each case its own lock and stop files, named per process
    $this->lock = storage_path('framework/testing/band-lock-guard-'.ThrowawayRoot::segment().'.lock');
    $this->stop = storage_path('framework/testing/band-lock-guard-'.ThrowawayRoot::segment().'.stop');
    $this->beat = storage_path('framework/testing/band-lock-guard-'.ThrowawayRoot::segment().'.beat');
    @unlink($this->lock);
    @unlink($this->stop);
    @unlink($this->beat);
    @unlink($this->lock.'.out');
});

afterEach(function () {
    // Let any child still waiting exit, then remove the scratch files
    touch($this->stop);

    // On Windows the lock is free while the child still runs, so also wait for the
    // heartbeat to stop, or the child would recreate it after it is removed
    $length = -1;
    $deadline = microtime(true) + 5;
    while (microtime(true) < $deadline) {
        clearstatcache(true, $this->beat);
        $current = is_file($this->beat) ? (int) filesize($this->beat) : 0;
        if ($current === $length) {
            break;
        }

        $length = $current;
        usleep(200000);
    }

    // Leave the stop file in place if the child is still running, so it can exit
    if (waitForBandLockRelease($this->lock)) {
        @unlink($this->lock);
        @unlink($this->stop);
        @unlink($this->beat);
        @unlink($this->lock.'.out');
    }
});

it('defaults to the band lock the testing environment uses', function () {
    // This process holds that lock, so the guard may report it held; either way
    // the path it names must be the one the testing environment locks. The two
    // spell the directory separators differently on Windows, so compare with
    // forward slashes
    $process = runBandLockGuard(null);

    expect(str_replace('\\', '/', $process->getOutput().$process->getErrorOutput()))
        ->toContain(str_replace('\\', '/', ContentMirrorBandLock::path()));
});

it('passes when no lock file exists', function () {
    $process = runBandLockGuard($this->lock);

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('does not exist');
});

it('passes when the lock file exists and nothing holds it', function () {
    touch($this->lock);

    $process = runBandLockGuard($this->lock);

    expect($process->getExitCode())->toBe(0)
        ->and($process->getOutput())->toContain('released');
});

it('fails, naming the lock and the holder, while an orphaned child holds an inherited lock where the platform keeps it, and passes once it exits', function () {
    $pid = orphanBandLockHolder($this->lock, $this->stop, $this->beat);
    expect($pid)->toBeGreaterThan(0);

    // The child is running once its heartbeat has started
    $before = waitForBandLockHeartbeat($this->beat, 0);
    expect($before)->toBeGreaterThan(0);

    // The parent has exited, so only the inherited descriptor can hold the lock
    $held = runBandLockGuard($this->lock);

    // The heartbeat still grows after the guard finished, so the child was alive
    // for the whole of the guard's run, not only before it
    clearstatcache(true, $this->beat);
    $atGuardEnd = (int) filesize($this->beat);
    expect(waitForBandLockHeartbeat($this->beat, $atGuardEnd))->toBeGreaterThan($atGuardEnd);

    if (PHP_OS_FAMILY === 'Windows') {
        // Windows dropped the lock when the parent that took it exited, so the
        // guard acquires it while the child that inherited the descriptor lives
        expect($held->getExitCode())->toBe(0)
            ->and($held->getOutput())->toContain('released');
    } else {
        expect($held->getExitCode())->toBe(1)
            ->and($held->getErrorOutput())->toContain('still held after the suite finished: '.$this->lock);
    }

    // Where lsof exists, the holder is named by its id and command line, and it
    // is the only one: the probe's own lsof shell is not reported as a holder
    if (trim((string) shell_exec('command -v lsof')) !== '') {
        expect($held->getErrorOutput())->toContain("pid {$pid}: ")
            ->and($held->getErrorOutput())->toContain('file_exists')
            ->and(substr_count($held->getErrorOutput(), '  pid '))->toBe(1);

        // The child must not hold the real band lock, or this test could leak it
        $realHolders = array_map('intval', array_filter(explode("\n", (string) shell_exec('lsof -t -- '.escapeshellarg(ContentMirrorBandLock::path()).' 2>/dev/null'))));
        expect($realHolders)->not->toContain($pid);
    }

    // Let the child exit, and the lock goes with it
    touch($this->stop);
    expect(waitForBandLockRelease($this->lock))->toBeTrue();

    $released = runBandLockGuard($this->lock);

    expect($released->getExitCode())->toBe(0)
        ->and($released->getOutput())->toContain('released');
});
