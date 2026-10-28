<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * Regression guard for the shard merge (#387). Runs
 * `scripts/merge-shard-reports.php` over throwaway shard fixtures and asserts it
 * writes one band report when the shards ran the band exactly once, and refuses,
 * naming what it found, for each way a sharded band can come back incomplete: a
 * shard that left no report, a class no shard ran, a case two shards ran, shards
 * that read different timings, and shards that listed different tests.
 *
 * Driven as a subprocess, like `SlowestClassGuardTest` and `SkipBaselineGuardTest`,
 * so the CLI contract ci.yml depends on (the exit code and what it prints) is
 * what is pinned. Framework-free, with fixtures under a per-run
 * `sys_get_temp_dir()` path, so it is parallel-safe.
 *
 * @command  php artisan test --compact tests/Feature/MergeShardReportsTest.php
 */
$root = dirname(__DIR__, 2);
$script = $root.'/scripts/merge-shard-reports.php';

/**
 * The band the fixtures shard: class => its case names.
 *
 * @var array<string, list<string>>
 */
$band = [
    'Tests\Feature\AlphaTest' => ['it one', 'it two'],
    'Tests\Feature\BetaTest' => ['it three'],
    'Tests\Unit\GammaTest' => ['it four', 'it five', 'it six'],
];

/**
 * Write one shard's three files into a fixture directory.
 *
 * The case names and classes are cast at the point of use: PHPStan does not apply
 * a docblock to a closure assigned to a variable.
 *
 * @param  string  $dir  The fixture directory
 * @param  int  $index  The shard's number
 * @param  array<mixed>  $classes  Class => case names this shard ran
 * @param  array<mixed>  $listed  Class => case names the shard's listing names
 * @param  string  $timings  The shard's timings note
 */
$writeShard = function (string $dir, int $index, array $classes, array $listed, string $timings = 'source=committed sha256=aaa'): void {
    $suites = '';

    // Write each class's cases as one suite, one second apiece
    foreach ($classes as $class => $names) {
        $cases = '';
        foreach ((array) $names as $name) {
            $cases .= '<testcase name="'.(is_string($name) ? $name : '').'" class="'.$class.'" assertions="1" time="1.0"/>';
        }
        $suites .= '<testsuite name="'.$class.'">'.$cases.'</testsuite>';
    }

    file_put_contents("{$dir}/junit-parallel-{$index}.xml", '<?xml version="1.0" encoding="UTF-8"?><testsuites>'.$suites.'</testsuites>');

    // Write the listing in the shape `pest --list-tests` prints it
    $listing = "\n  INFO  Available tests:\n\n";
    foreach ($listed as $class => $names) {
        foreach ((array) $names as $name) {
            $listing .= ' - P\\'.$class.'::__pest_evaluable_'.str_replace(' ', '_', is_string($name) ? $name : '')."\n";
        }
    }
    file_put_contents("{$dir}/pest-list-{$index}.txt", $listing);

    file_put_contents("{$dir}/shard-timings-{$index}.txt", $timings."\n");
};

/**
 * Make an empty fixture directory.
 */
$makeDir = function (): string {
    $dir = sys_get_temp_dir().'/merge-shards-'.bin2hex(random_bytes(6));
    mkdir($dir);

    return $dir;
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
 * Run the merge over a fixture directory for N shards.
 */
$merge = function (string $dir, int $total) use ($script): Process {
    $process = new Process([PHP_BINARY, $script, "--dir={$dir}", "--total={$total}", "--report={$dir}/merged.xml", "--timings={$dir}/shards.json"]);
    $process->run();

    return $process;
};

it('merges shards that ran the band exactly once, and writes the timings', function () use ($band, $writeShard, $makeDir, $cleanUp, $merge): void {
    $dir = $makeDir();

    try {
        $writeShard($dir, 1, ['Tests\Feature\AlphaTest' => $band['Tests\Feature\AlphaTest'], 'Tests\Feature\BetaTest' => $band['Tests\Feature\BetaTest']], $band);
        $writeShard($dir, 2, ['Tests\Unit\GammaTest' => $band['Tests\Unit\GammaTest']], $band);

        $process = $merge($dir, 2);
        $merged = (string) file_get_contents("{$dir}/merged.xml");
        $timings = json_decode((string) file_get_contents("{$dir}/shards.json"), true);

        expect($process->getExitCode())->toBe(0)
            ->and($process->getOutput())->toContain('2 shards ran 6 cases in 3 classes')
            ->and(substr_count($merged, '<testcase'))->toBe(6)
            ->and(is_array($timings) ? $timings['timings'] : null)->toBe([
                'Tests\Feature\AlphaTest' => 2.0,
                'Tests\Feature\BetaTest' => 1.0,
                'Tests\Unit\GammaTest' => 3.0,
            ]);
    } finally {
        $cleanUp($dir);
    }
});

it('refuses a shard that left no report', function () use ($band, $writeShard, $makeDir, $cleanUp, $merge): void {
    $dir = $makeDir();

    try {
        $writeShard($dir, 1, $band, $band);
        $writeShard($dir, 2, [], $band);
        unlink("{$dir}/junit-parallel-2.xml");

        $process = $merge($dir, 2);

        expect($process->getExitCode())->toBe(1)
            ->and($process->getErrorOutput())->toContain('shard 2/2: no readable junit-parallel-2.xml')
            ->and(is_file("{$dir}/merged.xml"))->toBeFalse();
    } finally {
        $cleanUp($dir);
    }
});

it('refuses a band in which no shard ran a class', function () use ($band, $writeShard, $makeDir, $cleanUp, $merge): void {
    $dir = $makeDir();

    try {
        $writeShard($dir, 1, ['Tests\Feature\AlphaTest' => $band['Tests\Feature\AlphaTest']], $band);
        $writeShard($dir, 2, ['Tests\Unit\GammaTest' => $band['Tests\Unit\GammaTest']], $band);

        $process = $merge($dir, 2);

        expect($process->getExitCode())->toBe(1)
            ->and($process->getErrorOutput())->toContain('did not run the band exactly once')
            ->and($process->getErrorOutput())->toContain('Tests\Feature\BetaTest: listed 1, ran 0');
    } finally {
        $cleanUp($dir);
    }
});

it('refuses a case that two shards ran', function () use ($band, $writeShard, $makeDir, $cleanUp, $merge): void {
    $dir = $makeDir();

    try {
        $writeShard($dir, 1, ['Tests\Feature\AlphaTest' => $band['Tests\Feature\AlphaTest'], 'Tests\Feature\BetaTest' => $band['Tests\Feature\BetaTest']], $band);
        $writeShard($dir, 2, ['Tests\Feature\BetaTest' => $band['Tests\Feature\BetaTest'], 'Tests\Unit\GammaTest' => $band['Tests\Unit\GammaTest']], $band);

        $process = $merge($dir, 2);

        expect($process->getExitCode())->toBe(1)
            ->and($process->getErrorOutput())->toContain('a test case ran in more than one shard')
            ->and($process->getErrorOutput())->toContain('Tests\Feature\BetaTest :: it three (shards 1 and 2)');
    } finally {
        $cleanUp($dir);
    }
});

it('refuses a class the listing does not name, such as a serial-group class in the band', function () use ($band, $writeShard, $makeDir, $cleanUp, $merge): void {
    $dir = $makeDir();

    try {
        $writeShard($dir, 1, [...$band, 'Tests\Feature\SerialTest' => ['it runs alone']], $band);

        $process = $merge($dir, 1);

        expect($process->getExitCode())->toBe(1)
            ->and($process->getErrorOutput())->toContain('Tests\Feature\SerialTest: listed 0, ran 1');
    } finally {
        $cleanUp($dir);
    }
});

it('refuses shards that read different timings', function () use ($band, $writeShard, $makeDir, $cleanUp, $merge): void {
    $dir = $makeDir();

    try {
        $writeShard($dir, 1, ['Tests\Feature\AlphaTest' => $band['Tests\Feature\AlphaTest'], 'Tests\Feature\BetaTest' => $band['Tests\Feature\BetaTest']], $band);
        $writeShard($dir, 2, ['Tests\Unit\GammaTest' => $band['Tests\Unit\GammaTest']], $band, 'source=cache sha256=bbb');

        $process = $merge($dir, 2);

        expect($process->getExitCode())->toBe(1)
            ->and($process->getErrorOutput())->toContain('the shards read different timings')
            ->and($process->getErrorOutput())->toContain('shard 2: source=cache sha256=bbb');
    } finally {
        $cleanUp($dir);
    }
});

it('refuses shards that listed different tests', function () use ($band, $writeShard, $makeDir, $cleanUp, $merge): void {
    $dir = $makeDir();

    try {
        $writeShard($dir, 1, ['Tests\Feature\AlphaTest' => $band['Tests\Feature\AlphaTest'], 'Tests\Feature\BetaTest' => $band['Tests\Feature\BetaTest']], $band);
        $writeShard($dir, 2, ['Tests\Unit\GammaTest' => $band['Tests\Unit\GammaTest']], ['Tests\Unit\GammaTest' => $band['Tests\Unit\GammaTest']]);

        $process = $merge($dir, 2);

        expect($process->getExitCode())->toBe(1)
            ->and($process->getErrorOutput())->toContain('shard 2 listed different tests from shard 1')
            ->and($process->getErrorOutput())->toContain('Tests\Feature\AlphaTest: shard 1 listed 2, shard 2 listed 0');
    } finally {
        $cleanUp($dir);
    }
});

it('refuses a usage error with exit 2', function () use ($script): void {
    $process = new Process([PHP_BINARY, $script, '--total=4']);
    $process->run();

    expect($process->getExitCode())->toBe(2)
        ->and($process->getErrorOutput())->toContain('Usage:');
});
