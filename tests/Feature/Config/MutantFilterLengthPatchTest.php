<?php

declare(strict_types=1);

use Pest\Mutate\MutationTest;

/**
 * Guards the `pest-plugin-mutate` filter-length patch (UAMS-Web/uams-statamic-archive#2578, UAMS-Web/uams-statamic-archive#3114), which stops a mutation run
 * scoring a mutant killed when its child's filter is too long to launch or too large to compile.
 *
 * On Windows every Symfony Process runs as `cmd.exe /V:ON /E:ON /D /C (...)`, whatever the
 * executable, and `cmd.exe` accepts at most 8,191 characters. The child's `--filter` names every
 * test covering the mutant, so a heavily covered mutant can push the line past that. The child then
 * exits 1 without running a test, and `hasFinished()` reads any non-zero exit as a kill. Measured in
 * `UAMS-Web/uamswp-migration-api-archive#296`: 8,008 characters ran 15 tests, and 8,400 exited 1 with no
 * output.
 *
 * On every platform, PHPUnit compiles the filter as `{<filter>}i` and matches with `@preg_match`, so a
 * joined pattern past PCRE's compiled-size limit matches no test: the child prints "No tests found."
 * and exits 1, which is scored as a kill too (UAMS-Web/uams-statamic-archive#3114).
 *
 * `MutationTest::childFilter()` is the patched seam. It takes the platform as an argument, so these
 * cases exercise the Windows branch on any machine. Retire this with the patch, per UAMS-Web/uams-statamic-archive#2578.
 *
 * @command  php artisan test --compact tests/Feature/Config/MutantFilterLengthPatchTest.php
 */
$mutationTestSource = dirname(__DIR__, 3).'/vendor/pestphp/pest-plugin-mutate/src/MutationTest.php';

/**
 * A command shaped like a mutant child's arguments before its `--filter`.
 *
 * @return array<int, string>
 */
function mutantChildCommand(): array
{
    return ['C:\\Herd\\uams-statamic\\vendor\\bin\\pest', '--path=app', '--bail'];
}

/**
 * `$count` covering-test patterns across `$classes` classes, each as the plugin builds them.
 *
 * @return array<int, string>
 */
function coveringTestPatterns(int $count, int $classes = 1): array
{
    $patterns = [];

    foreach (range(1, $count) as $index) {
        $patterns[] = 'CoveringClass'.($index % $classes).'Test::(.*)a.long.descriptive.test.name.number.'.$index.'.that.pads.it';
    }

    return $patterns;
}

it('builds the child filter through the length-aware seam', function () use ($mutationTestSource): void {
    $source = file_get_contents($mutationTestSource);

    if (! is_string($source)) {
        throw new RuntimeException("Unable to read the vendored source at [{$mutationTestSource}].");
    }

    // Absent means the patch is not applied: `composer install` does not re-patch an installed
    // package, so remove vendor/pestphp/pest-plugin-mutate and install again.
    expect($source)->toContain("'--filter='.(\$filter = self::childFilter(");
});

it('leaves a filter that fits unchanged', function (): void {
    $patterns = coveringTestPatterns(3);

    expect(MutationTest::childFilter($patterns, mutantChildCommand(), true))->toBe(implode('|', $patterns));
});

it('widens a Windows filter that does not fit to the covering test classes', function (): void {
    $patterns = coveringTestPatterns(120, 2);

    // Longer than cmd.exe accepts on its own, so the widening branch is what is exercised.
    expect(strlen(implode('|', $patterns)))->toBeGreaterThan(8191);

    expect(MutationTest::childFilter($patterns, mutantChildCommand(), true))
        ->toBe('CoveringClass1Test::|CoveringClass0Test::');
});

it('never widens a filter that compiles off Windows, where no cmd.exe limit applies', function (): void {
    $patterns = coveringTestPatterns(120, 2);

    expect(MutationTest::childFilter($patterns, mutantChildCommand(), false))->toBe(implode('|', $patterns));
});

it('refuses a mutant whose covering classes alone do not fit, naming it', function (): void {
    // Four hundred distinct classes: even one pattern per class is past the limit.
    $patterns = coveringTestPatterns(400, 400);

    expect(fn (): string => MutationTest::childFilter($patterns, mutantChildCommand(), true, 'app/Foo.php:12'))
        ->toThrow(RuntimeException::class, 'Cannot test the mutant at app/Foo.php:12 on Windows');
});

/**
 * `$count` covering-test patterns across `$classes` classes, padded with the `.{1,2}` the plugin
 * writes for each `__` in a Pest test name, which is what makes a real filter expensive to compile.
 *
 * @return array<int, string>
 */
function oversizedCoveringTestPatterns(int $count, int $classes): array
{
    $patterns = [];

    foreach (range(1, $count) as $index) {
        $patterns[] = 'CoveringClass'.($index % $classes).'Test::(.*)it.'.str_repeat('word.{1,2}', 12).$index;
    }

    return $patterns;
}

/**
 * Whether PHPUnit could compile `$filter` the way `CompiledNameFilter` wraps a `--filter`, with the
 * compile warning kept away from the test runner's own error handler.
 */
function phpUnitCompilesFilter(string $filter): bool
{
    set_error_handler(static fn (): bool => true);

    try {
        return preg_match('{'.$filter.'}i', '') !== false;
    } finally {
        restore_error_handler();
    }
}

it('widens a filter PCRE cannot compile to the covering test classes, on any platform', function (bool $windows): void {
    $patterns = oversizedCoveringTestPatterns(1000, 2);

    // The control: the joined pattern really does not compile, the way PHPUnit compiles it.
    expect(phpUnitCompilesFilter(implode('|', $patterns)))->toBeFalse()
        ->and(MutationTest::childFilter($patterns, mutantChildCommand(), $windows))
        ->toBe('CoveringClass1Test::|CoveringClass0Test::');
})->with(['off Windows' => false, 'on Windows' => true]);

it('leaves a long filter that compiles unchanged off Windows', function (): void {
    $patterns = oversizedCoveringTestPatterns(100, 2);

    // Past cmd.exe's limit, so only the platform keeps it from being widened.
    expect(strlen(implode('|', $patterns)))->toBeGreaterThan(8191)
        ->and(phpUnitCompilesFilter(implode('|', $patterns)))->toBeTrue()
        ->and(MutationTest::childFilter($patterns, mutantChildCommand(), false))->toBe(implode('|', $patterns));
});

it('refuses a mutant whose covering classes alone do not compile, naming it', function (): void {
    // One class per pattern, each costly enough that the class-level filter is past the limit too.
    $patterns = array_map(
        static fn (int $index): string => 'CoveringClass'.$index.str_repeat('.{1,2}', 40).'Test::(.*)it',
        range(1, 1000),
    );

    expect(fn (): string => MutationTest::childFilter($patterns, mutantChildCommand(), false, 'app/Bar.php:33'))
        ->toThrow(RuntimeException::class, 'Cannot test the mutant at app/Bar.php:33: its 1000 covering tests, even named by class, make a filter of');
});

it('restores the error handler it replaced while compiling', function (): void {
    $calls = 0;

    set_error_handler(static function () use (&$calls): bool {
        $calls++;

        return true;
    });

    try {
        MutationTest::childFilter(oversizedCoveringTestPatterns(1000, 2), mutantChildCommand(), false);

        // A warning raised now reaches the handler set above, not the one childFilter() installed.
        trigger_error('probe', E_USER_WARNING);
    } finally {
        restore_error_handler();
    }

    expect($calls)->toBe(1);
});

/*
 * The time limit a child gets (UAMS-Web/uams-statamic-archive#3202). The plugin sets every child's limit from the initial run
 * plus max(5 s, 20%) and scores a timeout as caught. A child whose filter was widened to its
 * covering classes runs every test in those classes, and can overrun that limit: measured in
 * `UAMS-Web/uamswp-migration-api#447` on Windows 11, a planted no-op on a line 266 tests cover
 * reported 5 timeouts against a 26.51 s initial run, counted as kills. A widened child now gets at
 * least three times the initial run; any other child keeps the plugin's limit, so a mutant that
 * really loops still times out.
 */

it('gives each child the limit the seam computes, from whether its filter was widened', function () use ($mutationTestSource): void {
    $source = file_get_contents($mutationTestSource);

    if (! is_string($source)) {
        throw new RuntimeException("Unable to read the vendored source at [{$mutationTestSource}].");
    }

    // Absent means the patch is not applied: `composer install` does not re-patch an installed
    // package, so run `composer patches-repatch`.
    expect($source)->toContain("timeout: self::childTimeout(\$this->initialTestSuiteDuration(), \$filter !== implode('|', \$filters)),")
        ->and($source)->not->toContain('calculateTimeout');
});

it("keeps the plugin's limit for a child that was not widened", function (float $initial, int $limit): void {
    expect(MutationTest::childTimeout($initial, false))->toBe($limit);
})->with([
    'a short run, where the 5 s floor applies' => [10.0, 15],
    'a longer run, where 20% applies' => [26.51, 31],
]);

it('gives a widened child at least three times the initial run', function (float $initial, int $limit): void {
    expect(MutationTest::childTimeout($initial, true))->toBe($limit);
})->with([
    'a longer run' => [26.51, 79],
    "a run so short that the plugin's limit is already longer" => [1.0, 6],
]);
