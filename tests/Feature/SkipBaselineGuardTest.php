<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * Regression guard for the skip-baseline check (UAMS-Web/uams-statamic-archive#1744). Runs
 * `scripts/check-skip-baseline.php` over throwaway JUnit fixtures and asserts it
 * fails a band that skipped more than its baseline and passes one that did not —
 * proving the guard is live, not vacuous.
 *
 * The gate treats "zero failures" as success, so a band that silently skips tests
 * reports PASS having tested less than it should have. During UAMS-Web/uams-statamic-archive#1726's validation
 * three tests self-skipped and the band still passed, caught only because the count
 * had been predicted in advance and checked by hand.
 *
 * Driven as a subprocess (like `tests/Feature/ParallelSafeTestsGuardTest.php`)
 * rather than by requiring the script: that pins the real CLI contract — exit
 * code and the names it prints — which is what ci.yml and local-ci.mjs actually
 * depend on. Pure subprocess check; framework-free, and its fixtures live under
 * a per-run `sys_get_temp_dir()` path, so it is parallel-safe.
 *
 * @command  php artisan test --compact tests/Feature/SkipBaselineGuardTest.php
 */
$root = dirname(__DIR__, 2);
$script = $root.'/scripts/check-skip-baseline.php';

/**
 * Write a JUnit report: `$skipped` skipped cases then `$passed` passing ones.
 *
 * Passing cases are self-closing exactly as PHPUnit writes them, which is what
 * makes "wraps a <skipped/> child" the discriminator — a parser that counted
 * `<testcase` would report every test as skipped.
 *
 * @return array{0: string, 1: string} The fixture directory and report path
 */
$writeReport = function (int $skipped, int $passed = 4): array {
    $dir = sys_get_temp_dir().'/skip-baseline-'.bin2hex(random_bytes(6));
    mkdir($dir);

    $cases = '';
    for ($i = 1; $i <= $skipped; $i++) {
        // A bare <skipped/> with no attributes — the shape PHPUnit 13's
        // JunitXmlLogger and paratest's merger actually write. A fixture that
        // invented a `message` attribute would prove a contract the pipeline
        // cannot produce.
        $cases .= '<testcase name="it skips '.$i.'" class="Tests\Feature\SomeTest" time="0.01">'
            .'<skipped/>'
            .'</testcase>';
    }
    for ($i = 1; $i <= $passed; $i++) {
        $cases .= '<testcase name="it passes '.$i.'" class="Tests\Feature\SomeTest" assertions="2" time="0.01"/>';
    }

    $report = $dir.'/junit.xml';
    file_put_contents(
        $report,
        '<?xml version="1.0" encoding="UTF-8"?><testsuites><testsuite name="d" tests="'
        .($skipped + $passed).'" failures="0" skipped="'.$skipped.'">'.$cases.'</testsuite></testsuites>'
    );

    return [$dir, $report];
};

/**
 * Write a baseline document declaring `$expected` skips for the `parallel` band.
 *
 * @return string The baseline file path
 */
$writeBaseline = function (string $dir, int $expected): string {
    $path = $dir.'/skip-baseline.json';
    file_put_contents($path, json_encode(['bands' => ['parallel' => ['expected' => $expected]]]));

    return $path;
};

/**
 * Remove a fixture directory and everything in it.
 */
$cleanUp = function (string $dir): void {
    foreach ((array) glob($dir.'/*') as $file) {
        if (is_string($file)) {
            unlink($file);
        }
    }

    rmdir($dir);
};

/**
 * Run the guard and return [exitCode, stdout.stderr].
 *
 * @return array{0: int, 1: string}
 */
$runGuard = function (string $report, string $baseline, string $band = 'parallel') use ($script, $root): array {
    // PHP_BINARY, not a bare `php`: the suite runs under an interpreter that is
    // not always the PATH default (Herd), and the repo root is the cwd both CI
    // and local-ci invoke from. Matches ParallelSafeTestsGuardTest.
    $process = new Process([PHP_BINARY, $script, '--report='.$report, '--band='.$band, '--baseline='.$baseline], $root);
    $process->run();

    return [$process->getExitCode() ?? -1, $process->getOutput().$process->getErrorOutput()];
};

it('passes a band sitting exactly at its baseline, with no new noise', function () use ($writeReport, $writeBaseline, $runGuard, $cleanUp): void {
    [$dir, $report] = $writeReport(2);
    [$code, $output] = $runGuard($report, $writeBaseline($dir, 2));

    expect($code)->toBe(0);
    expect($output)->toContain('Skip baseline OK');

    $cleanUp($dir);
});

it('fails a band above its baseline and names what stopped running', function () use ($writeReport, $writeBaseline, $runGuard, $cleanUp): void {
    // The UAMS-Web/uams-statamic-archive#1726 shape: baseline 2, three more tests self-skipped on top.
    [$dir, $report] = $writeReport(5);
    [$code, $output] = $runGuard($report, $writeBaseline($dir, 2));

    expect($code)->toBe(1);
    expect($output)->toContain('Skip baseline EXCEEDED');
    expect($output)->toContain('skipped 5, baseline 2 (+3)');
    // Naming the tests is the point — a bare count does not say what was lost.
    expect($output)->toContain('Tests\Feature\SomeTest::it skips 1');

    $cleanUp($dir);
});

it('does not count passing self-closing cases as skips', function () use ($writeReport, $writeBaseline, $runGuard, $cleanUp): void {
    // 40 passing, 0 skipped: a parser keying on `<testcase` would report 40.
    [$dir, $report] = $writeReport(0, 40);
    [$code, $output] = $runGuard($report, $writeBaseline($dir, 0));

    expect($code)->toBe(0);
    expect($output)->toContain('skipped 0');

    $cleanUp($dir);
});

it('passes a band below its baseline rather than punishing a restored test', function () use ($writeReport, $writeBaseline, $runGuard, $cleanUp): void {
    [$dir, $report] = $writeReport(0);
    [$code, $output] = $runGuard($report, $writeBaseline($dir, 2));

    expect($code)->toBe(0);
    expect($output)->toContain('below baseline');

    $cleanUp($dir);
});

it('names a skipped test whose sibling case carries a > in its name', function () use ($writeBaseline, $runGuard, $cleanUp): void {
    // libxml leaves `>` unescaped in an attribute value, so an attribute-scanning
    // regex ends its match there and re-enters the document mid-element. Verified
    // against exactly this report: the count survived, the identity did not.
    $dir = sys_get_temp_dir().'/skip-baseline-'.bin2hex(random_bytes(6));
    mkdir($dir);

    $report = $dir.'/junit.xml';
    file_put_contents(
        $report,
        '<?xml version="1.0" encoding="UTF-8"?><testsuites><testsuite name="d" tests="2" failures="0" skipped="1">'
        .'<testcase name="it prefers a > b" class="Tests\Feature\PassingTest" assertions="2" time="0.01"/>'
        .'<testcase name="it skips" class="Tests\Feature\SkipTest" time="0.01"><skipped/></testcase>'
        .'</testsuite></testsuites>'
    );

    [$code, $output] = $runGuard($report, $writeBaseline($dir, 0));

    expect($code)->toBe(1);
    expect($output)->toContain('Tests\Feature\SkipTest::it skips');
    expect($output)->not->toContain('(unnamed)');

    $cleanUp($dir);
});

it('fails a truncated report rather than reading it as zero skips', function () use ($writeBaseline, $runGuard, $cleanUp): void {
    // A band killed mid-write leaves unparseable XML. Counting zero there would
    // reinstate the silence this guard removes.
    $dir = sys_get_temp_dir().'/skip-baseline-'.bin2hex(random_bytes(6));
    mkdir($dir);

    $report = $dir.'/junit.xml';
    file_put_contents($report, '<testsuites><testsuite><testcase name="it truncates');

    [$code, $output] = $runGuard($report, $writeBaseline($dir, 2));

    expect($code)->toBe(1);
    expect($output)->toContain('not parseable XML');

    $cleanUp($dir);
});

it('fails cleanly when the baseline document is missing', function () use ($writeReport, $runGuard, $cleanUp): void {
    [$dir, $report] = $writeReport(0);

    [$code, $output] = $runGuard($report, $dir.'/absent-baseline.json');

    expect($code)->toBe(1);
    expect($output)->toContain('no baseline document');

    $cleanUp($dir);
});

it('fails when the band produced no report at all', function () use ($writeReport, $writeBaseline, $runGuard, $cleanUp): void {
    // An unverifiable band must not read as a pass — that is the same silence the
    // guard exists to remove.
    [$dir] = $writeReport(0);
    [$code, $output] = $runGuard($dir.'/absent.xml', $writeBaseline($dir, 2));

    expect($code)->toBe(1);
    expect($output)->toContain('cannot be verified');

    $cleanUp($dir);
});

it('fails loudly when a band has no recorded baseline', function () use ($writeReport, $runGuard, $cleanUp): void {
    // A typo in the band name must not silently disable the guard for that band.
    [$dir, $report] = $writeReport(9);
    $baseline = $dir.'/skip-baseline.json';
    file_put_contents($baseline, json_encode(['bands' => ['parallel' => ['expected' => 2]]]));

    [$code, $output] = $runGuard($report, $baseline, 'serial');

    // Exit 1, not an uncaught fatal — a misconfigured guard is a failed band.
    expect($code)->toBe(1);
    expect($output)->toContain('No skip baseline recorded');

    $cleanUp($dir);
});

it('resolves the committed baseline when --baseline is omitted', function () use ($writeReport, $script, $root, $cleanUp): void {
    // ci.yml invokes the guard with only --report and --band, so the default
    // path is the ONLY shape CI uses — and every other case here passes an
    // explicit --baseline, leaving it unexercised.
    [$dir, $report] = $writeReport(0);

    // Linux, so the shared count is the one read on any machine running this.
    $process = new Process([PHP_BINARY, $script, '--report='.$report, '--band=parallel', '--platform=Linux'], $root);
    $process->run();

    // Read the expected count rather than literalizing it: the baseline is
    // designed to be edited deliberately, and pinning `2` here would make a
    // legitimate revision red a test whose subject is the default *path*.
    /** @var array{bands: array<string, array{expected: int}>} $baseline */
    $baseline = json_decode((string) file_get_contents($root.'/tests/skip-baseline.json'), true);

    expect($process->getExitCode())->toBe(0);
    expect($process->getOutput().$process->getErrorOutput())
        ->toContain('baseline '.$baseline['bands']['parallel']['expected']);

    $cleanUp($dir);
});

it('holds a platform to its own count and every other platform to the shared one', function () use ($writeReport, $script, $root, $cleanUp): void {
    [$dir, $report] = $writeReport(5);
    $baseline = $dir.'/skip-baseline.json';
    file_put_contents($baseline, json_encode(['bands' => ['parallel' => [
        'expected' => 3,
        'platforms' => ['Windows' => ['expected' => 5]],
    ]]]));

    $run = function (string $platform) use ($script, $root, $report, $baseline): int {
        $process = new Process([PHP_BINARY, $script, '--report='.$report, '--band=parallel', '--baseline='.$baseline, '--platform='.$platform], $root);
        $process->run();

        return $process->getExitCode() ?? -1;
    };

    expect($run('Windows'))->toBe(0)
        ->and($run('Linux'))->toBe(1)
        ->and($run('Darwin'))->toBe(1);

    $cleanUp($dir);
});

it('holds the committed baseline for both bands the runner checks', function () use ($root): void {
    /** @var array{bands?: array<string, array{expected?: mixed}>} $baseline */
    $baseline = json_decode((string) file_get_contents($root.'/tests/skip-baseline.json'), true);

    expect($baseline['bands']['parallel']['expected'] ?? null)->toBeInt();
    expect($baseline['bands']['serial']['expected'] ?? null)->toBeInt();
});
