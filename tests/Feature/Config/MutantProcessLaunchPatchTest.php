<?php

declare(strict_types=1);

/**
 * Guards the `pest-plugin-mutate` mutant-process-launch patch (UAMS-Web/uams-statamic-archive#2322), which stops a mutation
 * run scoring every mutant killed without executing one. Each assertion below reads the
 * VENDORED source, so the test fails if the patch stops being applied — the failure this
 * closes is that `composer install` does not re-patch an already-installed package, so a
 * reused `vendor/` keeps the unpatched code while the install reports success.
 *
 * The defect: `MutationTest::start()` spawns each mutant from `$_SERVER['argv']`, reusing
 * argv[0] verbatim as the executable, and `hasFinished()` reads any non-zero exit as "the test
 * failed". A child that cannot be launched exits non-zero, so it is recorded as a kill —
 * silently, with exit 0 and a 100.00% score. Measured on Windows 11 Pro 10.0.26200 /
 * PHP 8.4.20 / v5.0.2: `vendor/bin/pest` and `php vendor/bin/pest` both reported
 * `20 tested, 0 untested, 100.00%` where `php vendor\bin\pest` reported `14 tested,
 * 6 untested, 70.00%` on the same class in the same tree.
 *
 * Retire this with the patch, per UAMS-Web/uams-statamic-archive#2322.
 *
 * @command  php artisan test --compact tests/Feature/Config/MutantProcessLaunchPatchTest.php
 */
$runner = dirname(__DIR__, 3).'/vendor/pestphp/pest-plugin-mutate/src/Tester/MutationTestRunner.php';

/**
 * Read the vendored runner source, failing loudly when it cannot be read.
 *
 * @param  string  $path  Absolute path to the vendored source file.
 * @return string Contents of the file.
 */
function mutantLaunchPatchSource(string $path): string
{
    $source = file_get_contents($path);

    if (! is_string($source)) {
        throw new RuntimeException("Unable to read the vendored source at [{$path}].");
    }

    return $source;
}

/**
 * Fail with the remedy rather than a whole-file diff when the patch is absent.
 *
 * `expect()->toContain()` takes needles only — it has no message parameter, and a string
 * in that position becomes an additional required substring — so an absent needle prints
 * the entire 350-line file and names neither what is missing nor what fixes it. This
 * throws first, with the one command that resolves it.
 *
 * THE REMEDY IS `patches-relock`, NOT `reinstall`, and the difference is the whole reason
 * this helper exists. `patches.lock.json` is tracked and is what `composer install`
 * applies from, so declaring a patch in `composer.json` alone leaves the lock and its
 * `_hash` unchanged. `composer reinstall <package>` then re-extracts and re-applies the
 * LOCKED set, silently omitting the new patch — measured here, and it is also what the
 * `_hash` that {@see patchStaleReason()} compares is derived from, so `scripts/local-ci.mjs`
 * sees nothing stale and skips its own re-patch.
 *
 * @param  string  $source  Vendored file contents.
 * @param  string  $needle  The patched text that must be present.
 */
function mutantLaunchPatchRequires(string $source, string $needle): void
{
    if (! str_contains($source, $needle)) {
        throw new RuntimeException(
            "The vendored pest-plugin-mutate is NOT patched: [{$needle}] is absent. "
            .'Declaring a patch in composer.json does not update the tracked patches.lock.json, '
            .'which is what an install applies from. Run: composer patches-relock, then remove '
            .'vendor/pestphp/pest-plugin-mutate and composer install. A plain `composer reinstall` '
            .'re-applies the LOCKED set and will not fix this.'
        );
    }
}

it('normalizes the separator in argv[0] so the mutant child can be launched', function () use ($runner): void {
    $source = mutantLaunchPatchSource($runner);

    // The fix proper. Without it a forward-slashed relative argv[0] reaches cmd.exe, which
    // cannot start it, and every mutant is scored killed.
    mutantLaunchPatchRequires($source, "str_replace('/', DIRECTORY_SEPARATOR, \$arguments[0])");
    expect($source)->toContain("str_replace('/', DIRECTORY_SEPARATOR, \$arguments[0])");
});

it('aborts the run when the mutant child still cannot be launched', function () use ($runner): void {
    $source = mutantLaunchPatchSource($runner);

    // The safety net, which is the half that cannot silently return: normalization fixes the
    // form this repository met, and any other command that cannot start must fail loudly instead
    // of producing a score.
    mutantLaunchPatchRequires($source, 'canLaunchMutantProcess');
    mutantLaunchPatchRequires($source, 'Cannot launch the mutant test process');
    expect($source)->toContain('canLaunchMutantProcess');
    expect($source)->toContain('Cannot launch the mutant test process');
});

it('still scores a started child from its exit code, so the patch changes no verdict', function () use ($runner): void {
    $source = mutantLaunchPatchSource($runner);

    // The negative case the patch must NOT change. It makes the child runnable; it does not
    // touch how a run that DID execute is judged, which stays in MutationTest::hasFinished().
    expect($source)->not->toContain('MutationTestResult::Tested');
});
