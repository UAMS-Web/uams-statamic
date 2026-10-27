<?php

declare(strict_types=1);

use Pest\Mutate\Support\StreamWrapper;

/**
 * Guards the `pest-plugin-mutate` Windows mutant-child patch (UAMS-Web/uams-statamic-archive#2524), which stops a mutation run
 * on Windows scoring a mutant killed when its child process never ran a test.
 *
 * Two defects, stacked, both measured on Windows 11 Pro 10.0.26200 / PHP 8.4.20 / v5.0.2 with
 * the UAMS-Web/uams-statamic-archive#2322 launch patch in place, on `App\Jobs\UnpublishExpiredClassifieds` (whose line 65
 * log-context mutant no test observes):
 *
 * 1. `MutationTest::start()` passed `'--filter="'.implode('|', $filters).'"'`. The literal quotes
 *    do not survive `cmd.exe`, which launches the `vendor\bin\pest` batch file, so the `|` between
 *    two or more test names was read as a pipe. Every child exited 255 having run nothing.
 * 2. `StreamWrapper::stream_lock()` returned `flock($this->resource, LOCK_SH)` whatever was
 *    asked. A mutant child routes every file operation through that wrapper, and on Windows a
 *    shared lock denies writes even through the handle holding it, so every
 *    `file_put_contents(..., LOCK_EX)` failed with `Permission denied` and the child exited 2.
 *
 * Either one alone scores every mutant killed. With both patched, the line 65 mutant is reported
 * as a survivor: `4 untested, 1 timeout, 6 tested` against `11 tested, 100.00%` before.
 *
 * The lock test below exercises the wrapper itself rather than reading its source. The exclusive
 * lock conflict it asserts holds on macOS and Linux too, where `flock()` locks are per open file,
 * so it goes red on any platform when the patch is absent. Retire this with the patch, per UAMS-Web/uams-statamic-archive#2524.
 *
 * @command  php artisan test --compact tests/Feature/Config/MutantWindowsChildPatchTest.php
 */
$mutationTestSource = dirname(__DIR__, 3).'/vendor/pestphp/pest-plugin-mutate/src/MutationTest.php';

/**
 * Run a callback with the plugin's `file` stream wrapper registered, as it is in a mutant child.
 *
 * The wrapper replaces PHP's own `file` protocol process-wide, so it is always restored, and the
 * original and override paths it requires are throwaway files outside the repository.
 *
 * @param  callable(string): mixed  $callback  Receives a scratch directory to write into.
 */
function withMutantChildStreamWrapper(callable $callback): mixed
{
    $directory = sys_get_temp_dir().'/mutant-child-lock-'.bin2hex(random_bytes(6));
    mkdir($directory, 0777, true);

    $original = $directory.'/Original.php';
    $override = $directory.'/Override.php';
    file_put_contents($original, '<?php');
    file_put_contents($override, '<?php');

    $started = false;

    try {
        StreamWrapper::start($original, $override);
        $started = true;

        return $callback($directory);
    } finally {
        // Restoring a protocol that was never replaced raises a notice, which would mask
        // whatever made start() throw.
        if ($started) {
            StreamWrapper::disable();
        }

        array_map('unlink', glob($directory.'/*') ?: []);
        rmdir($directory);
    }
}

it('passes the filter unquoted, so cmd.exe cannot split it at the pipe', function () use ($mutationTestSource): void {
    $source = file_get_contents($mutationTestSource);

    if (! is_string($source)) {
        throw new RuntimeException("Unable to read the vendored source at [{$mutationTestSource}].");
    }

    // Absent means the patch is not applied: run `composer patches-relock`, then remove
    // vendor/pestphp/pest-plugin-mutate and `composer install`, as the UAMS-Web/uams-statamic-archive#2322 guard explains.
    // Since UAMS-Web/uams-statamic-archive#2578 the unquoted filter comes from childFilter(), which joins the patterns itself;
    // since UAMS-Web/uams-statamic-archive#3202 it is kept in $filter, so the child's time limit can see whether it was widened.
    expect($source)->toContain("'--filter='.(\$filter = self::childFilter(\$filters")
        ->not->toContain("'--filter=\"'.implode('|', \$filters).'\"'");
});

it('writes a file under an exclusive lock through the mutant-child stream wrapper', function (): void {
    $written = withMutantChildStreamWrapper(function (string $directory): array {
        $target = $directory.'/cache-entry';

        // The exact call Laravel's file cache store makes, which failed on every retry in a
        // Windows mutant child.
        $bytes = file_put_contents($target, 'sixteen-byte-val', LOCK_EX);

        return [$bytes, file_get_contents($target)];
    });

    expect($written)->toBe([16, 'sixteen-byte-val']);
});

it('takes the lock that was asked for, so a second exclusive lock is refused', function (): void {
    $secondExclusive = withMutantChildStreamWrapper(function (string $directory): bool {
        $target = $directory.'/locked';
        file_put_contents($target, 'x');

        $first = fopen($target, 'r+');
        $second = fopen($target, 'r+');

        if ($first === false || $second === false) {
            throw new RuntimeException('Unable to open the lock fixture twice.');
        }

        try {
            expect(flock($first, LOCK_EX))->toBeTrue();

            // Unpatched, both requests became LOCK_SH, and two shared locks coexist, so this
            // non-blocking exclusive request succeeded.
            return flock($second, LOCK_EX | LOCK_NB);
        } finally {
            flock($first, LOCK_UN);
            fclose($first);
            fclose($second);
        }
    });

    expect($secondExclusive)->toBeFalse();
});
