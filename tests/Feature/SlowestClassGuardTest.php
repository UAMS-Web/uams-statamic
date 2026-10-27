<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * Regression guard for the slowest-class cost check (UAMS-Web/uams-statamic-archive#1890, UAMS-Web/uams-statamic-archive#2530). Runs
 * `scripts/check-slowest-class.php` over throwaway JUnit fixtures and asserts it
 * fails a band whose costliest class is over its share ceiling or the absolute
 * backstop, and passes one that is not — proving the guard is live, not vacuous.
 *
 * Both directions UAMS-Web/uams-statamic-archive#2530 asks for are pinned: a uniformly inflated band — what an
 * oversubscribed host produces — passes, and a single class growing into a
 * straggler on a quiet host fails.
 *
 * The gate reports PASS on any duration, so a change that reclaims the parallel
 * band's cost passes in silence. It has happened twice — the UAMS-Web/uams-statamic-archive#1348 route-count tax
 * (UAMS-Web/uams-statamic-archive#1521 → UAMS-Web/uams-statamic-archive#1573) and the 1085.3s class that bound an entire 1306s band (UAMS-Web/uams-statamic-archive#1797) —
 * and both were expensive to find precisely because nothing signalled them.
 *
 * Driven as a subprocess (like {@see SkipBaselineGuardTest} and
 * {@see ParallelSafeTestsGuardTest}) rather than by requiring the script: that pins
 * the real CLI contract — exit code, and the class names it prints — which is what
 * ci.yml and local-ci.mjs actually depend on. Framework-free, and its fixtures live
 * under a per-run `sys_get_temp_dir()` path, so it is parallel-safe.
 *
 * @command  php artisan test --compact tests/Feature/SlowestClassGuardTest.php
 */
$root = dirname(__DIR__, 2);
$script = $root.'/scripts/check-slowest-class.php';

/**
 * Write a JUnit report holding the given per-case durations.
 *
 * Each class's cases are emitted across TWO `<testsuite>` elements, because that is
 * what paratest's merger actually produces — one per worker process that ran part of
 * the class. A guard reading `<testsuite time="…">` would see only half.
 *
 * The keys and durations are cast at the point of use rather than declared in a
 * `@param` tag: PHPStan does not apply a docblock to a closure assigned to a
 * variable (verified against this config — the parameter stays `array`, its values
 * `mixed`), so a tag here would be decoration that level 10 rightly ignores.
 *
 * @return array{0: string, 1: string} The fixture directory and report path
 */
$writeReport = function (array $classes): array {
    $dir = sys_get_temp_dir().'/slowest-class-'.bin2hex(random_bytes(6));
    mkdir($dir);

    $suites = '';
    foreach ($classes as $class => $times) {
        $seconds = array_values((array) $times);
        $first = '';
        $second = '';
        foreach ($seconds as $index => $value) {
            // `is_numeric` rather than a bare cast: level 10 rejects casting mixed to
            // float, and a non-numeric fixture value should read as 0.0 rather than
            // as whatever PHP would coerce it to.
            $time = is_numeric($value) ? (float) $value : 0.0;
            $case = '<testcase name="it costs '.$index.'" class="'.$class.'" assertions="1" time="'.$time.'"/>';
            // Alternate cases between the two suites, so a correct guard has to sum
            // across both to reach the class total.
            $index % 2 === 0 ? $first .= $case : $second .= $case;
        }
        $suites .= '<testsuite name="'.$class.'" tests="'.count($seconds).'">'.$first.'</testsuite>'
            .'<testsuite name="'.$class.'" tests="'.count($seconds).'">'.$second.'</testsuite>';
    }

    $report = $dir.'/junit.xml';
    file_put_contents($report, '<?xml version="1.0" encoding="UTF-8"?><testsuites>'.$suites.'</testsuites>');

    return [$dir, $report];
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
$runGuard = function (string $report, ?float $ceiling = null, string $band = 'parallel', ?float $shareCeiling = null) use ($script, $root): array {
    // PHP_BINARY, not a bare `php`: the suite runs under an interpreter that is not
    // always the PATH default (Herd), and the repo root is the cwd both CI and
    // local-ci invoke from. Matches SkipBaselineGuardTest.
    $command = [PHP_BINARY, $script, '--report='.$report, '--band='.$band];
    if ($ceiling !== null) {
        $command[] = '--ceiling='.$ceiling;
    }
    if ($shareCeiling !== null) {
        $command[] = '--share-ceiling='.$shareCeiling;
    }

    $process = new Process($command, $root);
    $process->run();

    return [$process->getExitCode() ?? -1, $process->getOutput().$process->getErrorOutput()];
};

/**
 * Build a band's class table from a costliest class plus evenly-sized filler classes.
 *
 * Real bands carry hundreds of classes, and a share only means something against
 * that population; a two-class fixture would put any class at half the band.
 *
 * @return array<string, list<float>>
 */
$band = function (string $costliest, float $seconds, float $summed, int $fillers = 40): array {
    $classes = [$costliest => [$seconds]];
    $each = ($summed - $seconds) / $fillers;

    for ($index = 1; $index <= $fillers; $index++) {
        $classes['Tests\Feature\Filler'.$index.'Test'] = [$each];
    }

    return $classes;
};

it('sums each class across the split suites paratest merges, and names the costliest', function () use ($writeReport, $runGuard, $cleanUp): void {
    // Three classes, deliberately ordered so the winner is NOT the one with the most
    // cases: a guard counting tests rather than summing seconds would name Cheap.
    [$dir, $report] = $writeReport([
        'Tests\Feature\CheapTest' => [1.0, 1.0, 1.0, 1.0, 1.0, 1.0, 1.0, 1.0],
        'Tests\Feature\StragglerTest' => [30.0, 25.5],
        'Tests\Feature\MiddlingTest' => [10.0, 4.25],
    ]);

    [$code, $output] = $runGuard($report, 100.0, 'parallel', 80.0);

    expect($code)->toBe(0);
    expect($output)->toContain('Slowest class OK');
    // 30.0 + 25.5, i.e. summed across both of the class's `<testsuite>` elements.
    expect($output)->toContain('Tests\Feature\StragglerTest at 55.5s');
    // 55.5 of 77.75 summed across all three classes.
    expect($output)->toContain('71.38% of 77.8s summed class time');

    $cleanUp($dir);
});

it('passes a uniformly inflated band, which is what a contended host produces', function () use ($writeReport, $runGuard, $cleanUp, $band): void {
    // The UAMS-Web/uams-statamic-archive#2525 band as measured: identical test and assertion counts, the costliest
    // class inflated from 263.7s to 784.9s of 13299s summed on a host at a 15-minute
    // load average of 28.9 on 12 cores. It red the old 700s ceiling. Its share,
    // 5.90%, is where quiet bands on the same host sit, so it must pass.
    [$dir, $report] = $writeReport($band('Tests\Feature\SchemaJsonLdPodcastEpisodeTest', 784.9, 13299.0));

    [$code, $output] = $runGuard($report);

    expect($code)->toBe(0);
    expect($output)->toContain('Tests\Feature\SchemaJsonLdPodcastEpisodeTest at 784.9s, 5.90% of 13299.0s');

    $cleanUp($dir);
});

it('gives the same verdict on a band and on the same band inflated threefold', function () use ($writeReport, $runGuard, $cleanUp, $band): void {
    // Load invariance stated directly: scaling every class by one factor must not
    // move the share, so a quiet band and its contended twin read identically.
    $quiet = $band('Tests\Feature\SchemaJsonLdPodcastEpisodeTest', 144.9, 2834.1);
    $contended = array_map(fn (array $times): array => array_map(fn (float $time): float => $time * 3.0, $times), $quiet);

    [$quietDir, $quietReport] = $writeReport($quiet);
    [$contendedDir, $contendedReport] = $writeReport($contended);

    [$quietCode, $quietOutput] = $runGuard($quietReport);
    [$contendedCode, $contendedOutput] = $runGuard($contendedReport);

    expect($quietCode)->toBe(0);
    expect($contendedCode)->toBe(0);
    expect($quietOutput)->toContain('144.9s, 5.11%');
    expect($contendedOutput)->toContain('434.7s, 5.11%');

    $cleanUp($quietDir);
    $cleanUp($contendedDir);
});

it('fails a single class growing into a straggler on a quiet host', function () use ($writeReport, $runGuard, $cleanUp, $band): void {
    // A measured quiet band (144.9s of 2834.1s) in which only the costliest class
    // grows, to 400s. That is under the old 700s ceiling and far under the backstop,
    // so only the share can catch it: 400s of 3089.2s is 12.95%, past the point at
    // which a class binds a twelve-worker band.
    $classes = $band('Tests\Feature\SchemaJsonLdPodcastEpisodeTest', 144.9, 2834.1);
    $classes['Tests\Feature\SchemaJsonLdPodcastEpisodeTest'] = [400.0];

    [$dir, $report] = $writeReport($classes);

    [$code, $output] = $runGuard($report);

    expect($code)->toBe(1);
    expect($output)->toContain("Tests\Feature\SchemaJsonLdPodcastEpisodeTest is 12.95% of 3089.2s summed class time, share ceiling 7.50%");
    expect($output)->not->toContain('backstop 1500.0s (over by');
    // The runners-up are listed too, so the fix is scoped from the same output.
    expect($output)->toContain('Tests\Feature\Filler1Test');

    $cleanUp($dir);
});

it('fails a class over the absolute backstop even when its share is unremarkable', function () use ($writeReport, $runGuard, $cleanUp, $band): void {
    // Growth that hits every class at once — a per-test boot cost, like UAMS-Web/uams-statamic-archive#1348's
    // route-count tax — leaves every share where it was. The backstop is what sees
    // it. 1600s of 22600s is 7.08%, under the parallel share ceiling.
    [$dir, $report] = $writeReport($band('Tests\Feature\HugeTest', 1600.0, 22600.0, 30));

    [$code, $output] = $runGuard($report);

    expect($code)->toBe(1);
    expect($output)->toContain("Tests\Feature\HugeTest cost 1600.0s, backstop 1500.0s (over by 100.0s)");
    expect($output)->not->toContain('share ceiling 7.50% (over by');

    $cleanUp($dir);
});

it('names both breaches when a class is over its share and the backstop', function () use ($writeReport, $runGuard, $cleanUp): void {
    // The UAMS-Web/uams-statamic-archive#1797 shape in miniature: one class dwarfing the rest of the band.
    [$dir, $report] = $writeReport([
        'Tests\Feature\SchemaJsonLdLocationTest' => [600.0, 485.3],
        'Tests\Feature\CheapTest' => [2.0, 3.0],
    ]);

    [$code, $output] = $runGuard($report, 300.0);

    expect($code)->toBe(1);
    expect($output)->toContain('Slowest class EXCEEDED');
    expect($output)->toContain("Tests\Feature\SchemaJsonLdLocationTest cost 1085.3s, backstop 300.0s (over by 785.3s)");
    expect($output)->toContain("Tests\Feature\SchemaJsonLdLocationTest is 99.54% of 1090.3s summed class time");
    expect($output)->toContain('Tests\Feature\CheapTest');

    $cleanUp($dir);
});

it('judges the serial band against its own, wider share ceiling', function () use ($writeReport, $runGuard, $cleanUp, $band): void {
    // The worst serial share measured for UAMS-Web/uams-statamic-archive#2530, 24.31%, taken on a contended host.
    // It is inside the serial ceiling and far outside the parallel one, which is why
    // the two bands carry separate ceilings.
    [$dir, $report] = $writeReport($band('Tests\Feature\Fieldtypes\AssetsMinimumDimensionsEnforcementTest', 123.5, 508.1, 79));

    [$serialCode, $serialOutput] = $runGuard($report, null, 'serial');
    [$parallelCode, $parallelOutput] = $runGuard($report, null, 'parallel');

    expect($serialCode)->toBe(0);
    expect($serialOutput)->toContain('24.31% of 508.1s summed class time (share ceiling 40.00%');
    expect($parallelCode)->toBe(1);
    expect($parallelOutput)->toContain('share ceiling 7.50%');

    $cleanUp($dir);
});

it('holds a band with no ceiling of its own to the stricter parallel one', function () use ($writeReport, $runGuard, $cleanUp, $band): void {
    [$dir, $report] = $writeReport($band('Tests\Feature\SlowTest', 100.0, 1000.0));

    [$code, $output] = $runGuard($report, null, 'suite');

    expect($code)->toBe(1);
    expect($output)->toContain('is 10.00% of 1000.0s summed class time, share ceiling 7.50%');

    $cleanUp($dir);
});

it('applies the committed share ceilings and backstop when none is passed', function () use ($writeReport, $runGuard, $cleanUp, $band): void {
    // Pinned so an edit to a committed number is a visible, reviewable test change.
    [$dir, $report] = $writeReport($band('Tests\Feature\SlowTest', 100.0, 2000.0));

    [$parallelCode, $parallelOutput] = $runGuard($report, null, 'parallel');
    [$serialCode, $serialOutput] = $runGuard($report, null, 'serial');

    expect($parallelCode)->toBe(0);
    expect($parallelOutput)->toContain('(share ceiling 7.50%, backstop 1500.0s)');
    expect($serialCode)->toBe(0);
    expect($serialOutput)->toContain('(share ceiling 40.00%, backstop 1500.0s)');

    $cleanUp($dir);
});

it('lets --share-ceiling override the committed share ceiling', function () use ($writeReport, $runGuard, $cleanUp, $band): void {
    [$dir, $report] = $writeReport($band('Tests\Feature\SlowTest', 100.0, 2000.0));

    [$code, $output] = $runGuard($report, null, 'parallel', 4.0);

    expect($code)->toBe(1);
    expect($output)->toContain('is 5.00% of 2000.0s summed class time, share ceiling 4.00% (over by 1.00 points)');

    $cleanUp($dir);
});

it('passes a class exactly at its share ceiling and exactly at the backstop', function () use ($writeReport, $runGuard, $cleanUp, $band): void {
    // Both limits are inclusive. 75s of 1000s is exactly 7.50%, and 1500s of 22600s
    // is exactly the backstop at a share (6.64%) under the parallel ceiling.
    [$shareDir, $shareReport] = $writeReport($band('Tests\Feature\SlowTest', 75.0, 1000.0));
    [$backstopDir, $backstopReport] = $writeReport($band('Tests\Feature\HugeTest', 1500.0, 22600.0, 30));

    [$shareCode, $shareOutput] = $runGuard($shareReport);
    [$backstopCode, $backstopOutput] = $runGuard($backstopReport);

    expect($shareCode)->toBe(0);
    expect($shareOutput)->toContain('at 75.0s, 7.50% of 1000.0s');
    expect($backstopCode)->toBe(0);
    expect($backstopOutput)->toContain('at 1500.0s, 6.64% of 22600.0s');

    $cleanUp($shareDir);
    $cleanUp($backstopDir);
});

it('fails an override it cannot read rather than falling back to the committed number', function () use ($script, $root, $writeReport, $cleanUp, $band): void {
    // `--share-ceiling 5` reaches getopt as a flag with no value. Judging the band
    // against a ceiling nobody asked for would pass or fail the wrong question.
    [$dir, $report] = $writeReport($band('Tests\Feature\SlowTest', 100.0, 2000.0));

    foreach ([['--share-ceiling', '5'], ['--share-ceiling=abc'], ['--ceiling='], ['--ceiling=-3']] as $flags) {
        $process = new Process([PHP_BINARY, $script, '--report='.$report, '--band=parallel', ...$flags], $root);
        $process->run();

        expect($process->getExitCode())->toBe(1);
        expect($process->getErrorOutput())->toContain('needs a positive number');
    }

    $cleanUp($dir);
});

it('names the right class when a sibling test carries a > in its name', function () use ($runGuard, $cleanUp): void {
    // libxml leaves `>` unescaped in an attribute value, so an attribute-scanning
    // regex ends its match there and re-enters the document mid-element, shifting
    // every case boundary after it. Verified against exactly this shape in UAMS-Web/uams-statamic-archive#1744:
    // the count survived, the identity did not. DOM is why this passes.
    $dir = sys_get_temp_dir().'/slowest-class-'.bin2hex(random_bytes(6));
    mkdir($dir);

    $report = $dir.'/junit.xml';
    file_put_contents(
        $report,
        '<?xml version="1.0" encoding="UTF-8"?><testsuites><testsuite name="d" tests="2">'
        .'<testcase name="it prefers a > b" class="Tests\Feature\CheapTest" assertions="2" time="0.01"/>'
        .'<testcase name="it drags" class="Tests\Feature\StragglerTest" assertions="1" time="500"/>'
        .'</testsuite></testsuites>'
    );

    [$code, $output] = $runGuard($report, 300.0);

    expect($code)->toBe(1);
    expect($output)->toContain('Tests\Feature\StragglerTest cost 500.0s');
    expect($output)->not->toContain('(unattributed)');

    $cleanUp($dir);
});

it('fails a missing report rather than passing a band it never saw', function () use ($runGuard): void {
    [$code, $output] = $runGuard(sys_get_temp_dir().'/slowest-class-absent-'.bin2hex(random_bytes(6)).'.xml', 300.0);

    expect($code)->toBe(1);
    expect($output)->toContain('no JUnit report at');
});

it('fails a truncated report rather than reading it as zero cost', function () use ($runGuard, $cleanUp): void {
    // A band killed mid-write leaves unparseable XML. Reading that as "no class is
    // over the ceiling" is the exact silence this guard removes.
    $dir = sys_get_temp_dir().'/slowest-class-'.bin2hex(random_bytes(6));
    mkdir($dir);

    $report = $dir.'/junit.xml';
    file_put_contents($report, '<testsuites><testsuite><testcase name="it truncates');

    [$code, $output] = $runGuard($report, 300.0);

    expect($code)->toBe(1);
    expect($output)->toContain('not parseable XML');

    $cleanUp($dir);
});

it('fails a report that carries no durations at all', function () use ($runGuard, $cleanUp): void {
    // A well-formed report whose cases all carry a zero duration would otherwise
    // pass every ceiling trivially — a green that asserts nothing, the same class of
    // setup failure as the mutation smoke's `0 Mutations`.
    $dir = sys_get_temp_dir().'/slowest-class-'.bin2hex(random_bytes(6));
    mkdir($dir);

    $report = $dir.'/junit.xml';
    file_put_contents(
        $report,
        '<?xml version="1.0" encoding="UTF-8"?><testsuites><testsuite name="d" tests="2">'
        .'<testcase name="it has no time" class="Tests\Feature\CheapTest"/>'
        .'<testcase name="it also has none" class="Tests\Feature\OtherTest" time="0"/>'
        .'</testsuite></testsuites>'
    );

    [$code, $output] = $runGuard($report, 300.0);

    expect($code)->toBe(1);
    expect($output)->toContain('carries no test durations');

    $cleanUp($dir);
});

it('fails a well-formed report holding no test cases', function () use ($runGuard, $cleanUp): void {
    // An empty `<testsuites/>` is what a band that collected nothing writes. It is
    // parseable and it is not a pass.
    $dir = sys_get_temp_dir().'/slowest-class-'.bin2hex(random_bytes(6));
    mkdir($dir);

    $report = $dir.'/junit.xml';
    file_put_contents($report, '<?xml version="1.0" encoding="UTF-8"?><testsuites/>');

    [$code, $output] = $runGuard($report, 300.0);

    expect($code)->toBe(1);
    expect($output)->toContain('no <testcase> elements');

    $cleanUp($dir);
});
