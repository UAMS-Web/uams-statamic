<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * Regression guard for `scripts/check-mutate-ignore-breadth.php` (UAMS-Web/uams-statamic-archive#3160), which counts how
 * many mutants each `@pest-mutate-ignore` annotation removes.
 *
 * THE TWO ARMS ARE THE TICKET'S. `ConditionalLogicEvaluator.php` as of `faf7dac66` carries
 * a `TrueToFalse` annotation at line 188 that removes three mutants, and the text auditor
 * reported it clean; the same file as of `2ee8781cf` carries ten annotations that each
 * remove exactly one. Both copies are fixtures with a `.php.txt` extension, so no scanner
 * treats them as source, and the check is pointed at them as single files.
 *
 * The other cases pin the remaining contract: an annotation that removes nothing is flagged
 * separately from one that removes too much, a sentence that only mentions the marker is not
 * an annotation, a baseline accounts for known findings and fails on a stale entry, and every
 * way the check can fail to run exits 2 rather than reporting clean.
 *
 * Driven as a subprocess like `MutateIgnoreAuditorTest`, pinning the CLI's exit code and
 * printed text. Per-run temp paths, so it is parallel-safe.
 *
 * @command  php artisan test --compact tests/Feature/MutateIgnoreBreadthTest.php
 */
$script = dirname(__DIR__, 2).'/scripts/check-mutate-ignore-breadth.php';
$fixtures = dirname(__DIR__).'/Fixtures/MutateIgnoreBreadth';

/**
 * Run the check with the given arguments and environment.
 *
 * @param  array<int, string>  $arguments  Paths and options passed to the script.
 * @param  array<string, string>  $env  Extra environment for the run.
 * @return array{0: int|null, 1: string} The exit code and the combined output.
 */
$run = function (array $arguments, array $env = []) use ($script): array {
    // Narrow both lists to strings so the process gets exactly what the CLI receives
    $command = [PHP_BINARY, $script];
    $environment = [];

    foreach ($arguments as $argument) {
        if (is_string($argument)) {
            $command[] = $argument;
        }
    }

    foreach ($env as $name => $value) {
        if (is_string($name) && is_string($value)) {
            $environment[$name] = $value;
        }
    }

    $process = new Process($command, null, $environment ?: null);
    $process->setTimeout(300);
    $process->run();

    return [$process->getExitCode(), $process->getOutput().$process->getErrorOutput()];
};

/**
 * Write a throwaway PHP file into a fresh temp directory.
 *
 * @param  string  $body  The file's source.
 * @return string The file's path.
 */
$fixture = function (string $body): string {
    $dir = sys_get_temp_dir().'/mutate-ignore-breadth-'.bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);
    file_put_contents($dir.'/Fixture.php', $body);

    return $dir.'/Fixture.php';
};

/**
 * The form the script prints a path in, and keys a baseline entry by.
 *
 * The script joins every scanned path with `/`, so on Windows the `\` that `dirname()` and
 * `sys_get_temp_dir()` return would never match its output.
 *
 * @param  string  $path  A path as the test built it.
 * @return string The same path joined with `/`.
 */
$printed = fn (string $path): string => str_replace('\\', '/', $path);

it('flags the faf7dac66 annotation at line 188 as removing three TrueToFalse mutants', function () use ($run, $fixtures, $printed) {
    $path = $fixtures.'/ConditionalLogicEvaluator.faf7dac66.php.txt';

    [$code, $output] = $run([$path]);

    expect($code)->toBe(1)
        ->and($output)->toContain($printed($path).":188\n  removes MORE than one mutant of a named mutator\n  TrueToFalse=3\n")
        ->and($output)->toContain('1 annotation(s) flagged');
});

it('flags nothing in the 2ee8781cf copy, after measuring all ten annotations', function () use ($run, $fixtures) {
    [$code, $output] = $run([$fixtures.'/ConditionalLogicEvaluator.2ee8781cf.php.txt']);

    expect($code)->toBe(0)
        ->and($output)->toContain('10 annotation(s) measured in 1 file(s)')
        ->and($output)->toContain('every annotation removes exactly one mutant per named mutator');
});

it('flags an annotation that removes no mutant, apart from one that removes too many', function () use ($run, $fixture, $printed) {
    $path = $fixture(<<<'PHP'
        <?php

        function nothing(): array
        {
            // @pest-mutate-ignore: AlwaysReturnEmptyArray
            return [];
        }
        PHP);

    [$code, $output] = $run([$path]);

    expect($code)->toBe(1)
        ->and($output)->toContain($printed($path).":5\n  removes NO mutant\n  AlwaysReturnEmptyArray=0\n")
        ->and($output)->not->toContain('MORE than one');
});

it('passes an annotation that removes exactly one mutant, and ignores prose naming the marker', function () use ($run, $fixture) {
    $path = $fixture(<<<'PHP'
        <?php

        /**
         * A sentence about @pest-mutate-ignore is documentation, not an annotation.
         */
        function one(int $a): bool
        {
            // @pest-mutate-ignore: GreaterToGreaterOrEqual
            return $a > 1;
        }
        PHP);

    [$code, $output] = $run([$path]);

    expect($code)->toBe(0)
        ->and($output)->toContain('1 annotation(s) measured in 1 file(s)');
});

/*
 * THE SHARED-NAME ARM (UAMS-Web/uams-statamic-archive#3280). Five mutator names belong to two plugin classes each, an
 * `Arithmetic` one for the binary operator and an `Assignment` one for its compound form, and
 * the plugin ignores by name, so one annotation covers both. A map keyed by name held whichever
 * class the directory walk returned last, so one shape or the other measured as removing
 * nothing depending on the platform's walk order. Both shapes run, so the case fails on either
 * order when the fix is removed.
 */
it('measures an annotation naming a mutator that two classes share against both classes', function (string $statement) use ($run, $fixture) {
    $path = $fixture(<<<PHP
        <?php

        function spock(int \$a, int \$b): int
        {
            // @pest-mutate-ignore: BitwiseOrToBitwiseAnd
            {$statement}

            return \$a;
        }
        PHP);

    [$code, $output] = $run([$path]);

    expect($code)->toBe(0)
        ->and($output)->toContain('1 annotation(s) measured in 1 file(s)')
        ->and($output)->not->toContain('removes NO mutant');
})->with([
    'binary operator' => ['$a = $a | $b;'],
    'compound assignment' => ['$a |= $b;'],
]);

it('still flags a shared-name annotation above a statement neither class mutates', function () use ($run, $fixture) {
    $path = $fixture(<<<'PHP'
        <?php

        function uhura(int $a, int $b): int
        {
            // @pest-mutate-ignore: BitwiseOrToBitwiseAnd
            return $a + $b;
        }
        PHP);

    [$code, $output] = $run([$path]);

    expect($code)->toBe(1)
        ->and($output)->toContain("removes NO mutant\n  BitwiseOrToBitwiseAnd=0\n");
});

it('accounts for a baselined finding and fails on a new one or a stale entry', function () use ($run, $fixtures, $printed) {
    $path = $fixtures.'/ConditionalLogicEvaluator.faf7dac66.php.txt';
    $dir = sys_get_temp_dir().'/mutate-ignore-breadth-'.bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);
    $baseline = $dir.'/baseline.json';

    // Record the current finding, then a run against that baseline passes
    [$writeCode] = $run(['--write-baseline='.$baseline, $path]);
    [$code, $output] = $run(['--baseline='.$baseline, $path]);

    expect($writeCode)->toBe(0)
        ->and($code)->toBe(0)
        ->and($output)->toContain('1 finding(s) accounted for by')
        ->and($output)->toContain('no annotation flagged outside the baseline');

    // An empty baseline leaves the finding new, so the run fails
    file_put_contents($baseline, '{"findings": []}');
    [$newCode, $newOutput] = $run(['--baseline='.$baseline, $path]);

    expect($newCode)->toBe(1)
        ->and($newOutput)->toContain('1 annotation(s) flagged, 0 stale baseline entry');

    // An entry for the scanned file that no longer occurs is stale, so the run fails
    file_put_contents($baseline, json_encode(['findings' => [
        ['path' => $printed($path), 'marker' => '// @pest-mutate-ignore: TrueToFalse', 'finding' => 'x', 'detail' => 'TrueToFalse=3'],
        ['path' => $printed($path), 'marker' => '// gone', 'finding' => 'x', 'detail' => 'TrueToFalse=2'],
    ]]));
    [$staleCode, $staleOutput] = $run(['--baseline='.$baseline, $path]);

    expect($staleCode)->toBe(1)
        ->and($staleOutput)->toContain('baseline entry no longer occurs')
        ->and($staleOutput)->toContain('0 annotation(s) flagged, 1 stale baseline entry');
});

it('exits 2 rather than reporting clean when it cannot run', function (array $arguments, array $env, string $message) use ($run, $fixture) {
    // A file that does not parse stands in for BROKEN
    foreach ($arguments as $index => $argument) {
        if ($argument === 'BROKEN') {
            $arguments[$index] = $fixture("<?php\n// @pest-mutate-ignore\nfunction (\n");
        }
    }

    // An empty directory stands in for a plugin that ships no mutator classes
    foreach ($env as $name => $value) {
        if ($value === 'EMPTY') {
            $env[$name] = dirname($fixture('<?php'));
        }
    }

    [$code, $output] = $run($arguments, $env);

    expect($code)->toBe(2)
        ->and($output)->toContain($message);
})->with([
    'no mutator classes resolved' => [['app'], ['MUTATE_IGNORE_BREADTH_MUTATORS_PATH' => 'EMPTY'], 'no mutator classes resolved'],
    'a file that does not parse' => [['BROKEN'], [], 'cannot parse'],
    'a missing path' => [['does-not-exist'], [], 'not a file or directory'],
    'an unknown option' => [['--nope'], [], 'unknown option'],
    'an unreadable baseline' => [['--baseline=does-not-exist.json', 'app/Support/Forms'], [], 'cannot read baseline'],
]);

it('prints how many mutator classes it resolved from the installed plugin', function () use ($run, $fixtures) {
    [, $output] = $run([$fixtures.'/ConditionalLogicEvaluator.2ee8781cf.php.txt']);

    expect($output)->toMatch('/check-mutate-ignore-breadth: [1-9]\d* mutator classes resolved from the installed plugin/');
});
