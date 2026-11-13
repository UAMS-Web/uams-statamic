<?php

declare(strict_types=1);

use Statamic\Statamic;
use Tests\Support\StatamicStaticRegistries;
use Tests\TestCase;

// cspell:ignore Bergmann Termwind

uses(TestCase::class);

/**
 * Guards the per-test application boot against growing with the number of earlier boots (UAMS-Web/uams-statamic#540).
 *
 * A static list the boot appends to and nothing empties makes boot N do N sets of
 * that work, so a file's runtime grows with the square of its test count. Timing
 * the boot would be a flaky way to see that; counting what the boot left behind is
 * not. Both tests below count.
 *
 * The first boots a fresh test case, which stands in for the next test, and
 * requires Statamic's route and install lists to hold the same number of entries
 * after that boot as after this test's own. The second runs three times through
 * the real test lifecycle and requires every static array outside the test
 * framework to be the same size in the third run as in the second, so a list some
 * other package starts growing fails here too, naming the property.
 */
it("leaves Statamic's route and install lists the same size after another boot", function (): void {
    $afterThisBoot = StatamicStaticRegistries::sizes();

    // Positive control: the lists are populated, so an equal count below is a
    // count of real entries, not two empty reads.
    expect($afterThisBoot['cpRoutes'])->toBeGreaterThan(0)
        ->and($afterThisBoot['webRoutes'])->toBeGreaterThan(0);

    $probe = new ($this::class)('boot_growth_probe');
    $probe->createApplication();

    expect(StatamicStaticRegistries::sizes())->toBe($afterThisBoot);
});

/**
 * Size of every static array declared by a user-land class outside the test framework, keyed `Class::$property`.
 *
 * @return array<string, int>
 */
function bootGrowthStaticArraySizes(): array
{
    $sizes = [];

    foreach (get_declared_classes() as $class) {
        if (preg_match('/^(Pest|PHPUnit|SebastianBergmann|P\\\\|Whoops|NunoMaduro|Termwind|Mockery|ParaTest|Tests\\\\Feature)\\\\?/', $class) === 1) {
            continue;
        }

        $reflection = new ReflectionClass($class);

        if ($reflection->isInternal()) {
            continue;
        }

        foreach ($reflection->getProperties(ReflectionProperty::IS_STATIC) as $property) {
            if ($property->getDeclaringClass()->getName() !== $class || ! $property->isInitialized()) {
                continue;
            }

            $value = $property->getValue();

            if (is_array($value)) {
                $sizes[$class.'::$'.$property->getName()] = count($value);
            }
        }
    }

    return $sizes;
}

/**
 * The sizes the second run recorded, shared across the dataset's runs.
 *
 * A named function holds it because Pest binds a fresh copy of the test closure
 * for each run, which would not share a `static` declared inside the closure.
 *
 * @param  array<string, int>|null  $record  Sizes to store, or null to only read.
 * @return array<string, int>|null The stored sizes, or null before the second run.
 */
function bootGrowthBaseline(?array $record = null): ?array
{
    /** @var array<string, int>|null $baseline */
    static $baseline = null;

    if ($record !== null) {
        $baseline = $record;
    }

    return $baseline;
}

it('leaves no static array larger in a later test than in an earlier one', function (int $run): void {
    $sizes = bootGrowthStaticArraySizes();

    // Positive control: the sweep reads the list the fix resets, so a list of that
    // kind growing would be seen.
    expect($sizes)->toHaveKey(Statamic::class.'::$cpRoutes');

    // Run 1 is skipped as a baseline: the first test in a process loads classes and
    // fills one-time caches that later tests only read.
    if ($run === 2) {
        bootGrowthBaseline($sizes);
    }

    $baseline = bootGrowthBaseline();

    if ($run === 3 && $baseline !== null) {
        $grown = [];

        foreach ($sizes as $key => $size) {
            if (isset($baseline[$key]) && $size > $baseline[$key]) {
                $grown[$key] = $baseline[$key].' -> '.$size;
            }
        }

        expect($grown)->toBe([]);
    }
})->with([1, 2, 3]);
