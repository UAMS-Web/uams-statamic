<?php

declare(strict_types=1);

/**
 * Guards the `pest-plugin-mutate` run-filter patch (UAMS-Web/uams-statamic-archive#2570), which stops a mutation run that was
 * given `--filter` from scoring every mutant killed. The assertions read the VENDORED source, so
 * the test fails if the patch stops being applied: `composer install` does not re-patch an
 * already-installed package, so a reused `vendor/` keeps the unpatched code while the install
 * reports success.
 *
 * The defect: `MutationTest::start()` builds each mutant child from the run's own arguments,
 * which still carry the run's `--filter`, then appends a second `--filter` naming the covering
 * tests. PHPUnit warns "Option --filter cannot be used more than once" and exits 1 even when
 * every test passes, and `hasFinished()` reads any non-zero exit as a kill. Measured on macOS /
 * PHP 8.4 / v5.0.2 against `App\Widgets\IncompletePublishedEntries` with only the
 * `DecrementInteger` mutator: `tests/Feature --filter='IncompletePublishedEntries' --parallel`
 * reported `15 tested, 100.00%`. Four of those children exited 1 with every test passing, and the
 * same mutants selected by file path instead reported `4 untested, 11 tested`.
 *
 * Retire this with the patch, per UAMS-Web/uams-statamic-archive#2570.
 *
 * @command  php artisan test --compact tests/Feature/Config/MutantRunFilterPatchTest.php
 */
$mutationTest = dirname(__DIR__, 3).'/vendor/pestphp/pest-plugin-mutate/src/MutationTest.php';

/**
 * Read the vendored source, failing loudly when it cannot be read.
 *
 * @param  string  $path  Absolute path to the vendored source file.
 * @return string Contents of the file.
 */
function mutantRunFilterPatchSource(string $path): string
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
 * `expect()->toContain()` takes needles only, so an absent needle would print the whole file and
 * name neither what is missing nor what fixes it. This throws first, with the command that does.
 *
 * @param  string  $source  Vendored file contents.
 * @param  string  $needle  The patched text that must be present.
 */
function mutantRunFilterPatchRequires(string $source, string $needle): void
{
    if (! str_contains($source, $needle)) {
        throw new RuntimeException(
            "The vendored pest-plugin-mutate is NOT patched: [{$needle}] is absent. "
            .'Run: composer patches-relock, then remove vendor/pestphp/pest-plugin-mutate and '
            .'composer install. A plain `composer reinstall` re-applies the LOCKED set and will '
            .'not fix this.'
        );
    }
}

it('drops the run\'s own --filter, in both spellings, from each mutant child', function () use ($mutationTest): void {
    $source = mutantRunFilterPatchSource($mutationTest);

    // `--filter=<pattern>` is one argument; `--filter <pattern>` is two, so its value must go too.
    mutantRunFilterPatchRequires($source, "str_starts_with(\$argument, '--filter=')");
    mutantRunFilterPatchRequires($source, "\$argument === '--filter'");
    expect($source)->toContain("str_starts_with(\$argument, '--filter=')")
        ->and($source)->toContain("\$argument === '--filter'");
});

it('still gives each child exactly the covering-test --filter', function () use ($mutationTest): void {
    $source = mutantRunFilterPatchSource($mutationTest);

    // The child's own selection must survive the patch, or it would run every test in the path.
    // Since UAMS-Web/uams-statamic-archive#2578 the filter is built by childFilter(), which widens it only when a Windows
    // command line would not fit. Since UAMS-Web/uams-statamic-archive#3202 it is kept in $filter for the child's time limit.
    expect(substr_count($source, "'--filter='.(\$filter = self::childFilter(\$filters"))->toBe(1);
});
