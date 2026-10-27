<?php

declare(strict_types=1);

/**
 * Guards the `pest-plugin-mutate` parallel-runner-options patch (UAMS-Web/uams-statamic-archive#2926, UAMS-Web/uams-statamic-archive#2947), which stops a
 * parallel mutation run given one of the parallel runner's own options from scoring every mutant
 * killed. The assertions read the VENDORED source, so the test fails if the patch stops being
 * applied: `composer install` does not re-patch an already-installed package, so a reused
 * `vendor/` keeps the unpatched code while the install reports success.
 *
 * The defect: `MutationTest::start()` builds each mutant child from the run's own arguments. The
 * plugin strips its own options (`--parallel`, `--class`, …) but not the parallel runner's, and a
 * mutant child is a plain run that rejects each of them with `Unknown option` and exits 2.
 * `hasFinished()` reads any non-zero exit as a kill. Measured on macOS / PHP 8.4 / v5.0.2:
 * `--passthru-php` made `SchemaThingFromRelationshipResolver` report `993 tested, 100.00%` in a
 * 17.3s mutation phase (UAMS-Web/uams-statamic-archive#2926), and `--processes=6` made `FieldRequiredHelper` report
 * `41 tested, 100.00%` in 1.19s (UAMS-Web/uams-statamic-archive#2947). In a plain run, each option listed below exits 2, while
 * `-p`, `-v` and `--shard` are accepted.
 *
 * Retire this with the patch, per UAMS-Web/uams-statamic-archive#2947.
 *
 * @command  php artisan test --compact tests/Feature/Config/MutantParallelRunnerOptionsPatchTest.php
 */
$mutationTest = dirname(__DIR__, 3).'/vendor/pestphp/pest-plugin-mutate/src/MutationTest.php';

/**
 * Read the vendored source, failing loudly when it cannot be read.
 *
 * @param  string  $path  Absolute path to the vendored source file.
 * @return string Contents of the file.
 */
function mutantParallelRunnerOptionsPatchSource(string $path): string
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
function mutantParallelRunnerOptionsPatchRequires(string $source, string $needle): void
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

it('drops every parallel-runner option a plain child rejects', function (string $option) use ($mutationTest): void {
    $source = mutantParallelRunnerOptionsPatchSource($mutationTest);

    mutantParallelRunnerOptionsPatchRequires($source, "'{$option}'");
    expect($source)->toContain("'{$option}'");
})->with([
    'value option --max-batch-size' => ['--max-batch-size'],
    'value option --max-processes' => ['--max-processes'],
    'value option --passthru-php' => ['--passthru-php'],
    'value option --processes' => ['--processes'],
    'value option --runner' => ['--runner'],
    'value option --shard-test-distribution' => ['--shard-test-distribution'],
    'value option --shard-test-distribution-seed' => ['--shard-test-distribution-seed'],
    'value option --tmp-dir' => ['--tmp-dir'],
    'flag --functional' => ['--functional'],
    'flag --no-test-tokens' => ['--no-test-tokens'],
    'flag --verbose' => ['--verbose'],
]);

it('drops both spellings of a value option and of -m', function () use ($mutationTest): void {
    $source = mutantParallelRunnerOptionsPatchSource($mutationTest);

    // `--option=value` is one argument; `--option value` and `-m value` are two, so the value
    // must go too; `-m<value>` is one.
    foreach (["str_starts_with(\$argument, \$option.'=')", "\$argument === '-m'", "preg_match('/^-m\\S/', \$argument)"] as $needle) {
        mutantParallelRunnerOptionsPatchRequires($source, $needle);
    }

    expect($source)->toContain("str_starts_with(\$argument, \$option.'=')")
        ->and($source)->toContain("\$argument === '-m'")
        ->and($source)->toContain("preg_match('/^-m\\S/', \$argument)");
});

it('drops them from the arguments the child is built from, after the run filter is dropped', function () use ($mutationTest): void {
    $source = mutantParallelRunnerOptionsPatchSource($mutationTest);

    // The drop must feed the arguments the child Process is built from. It runs on the list the
    // run-filter patch (UAMS-Web/uams-statamic-archive#2570) already produced, so neither patch undoes the other.
    expect(positionOf($source, '$filteredArguments = $withoutRunFilter;'))
        ->toBeLessThan(positionOf($source, '$filteredArguments = $withoutParallelRunnerOptions;'))
        ->and(positionOf($source, '$filteredArguments = $withoutParallelRunnerOptions;'))
        ->toBeLessThan(positionOf($source, '$process = new Process('));
});
