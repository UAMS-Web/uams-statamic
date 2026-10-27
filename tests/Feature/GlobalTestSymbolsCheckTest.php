<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * The shared `check-global-test-symbols.php` as this repository runs it (UAMS-Web/uams-statamic-archive#3112).
 *
 * The script replaced Rules C and D of `scripts/check-parallel-safe-tests.php`, and
 * its own tests live with it in `UAMS-Web/uams-claude-skills`. These cases pin what
 * changed for this suite: the `test-isolation-guard` job and the local-CI
 * `isolation` job run it over `tests`, a duplicated global symbol or a no-op import
 * fails it, and a whole-file `serial` quarantine no longer exempts a file, because
 * the serial tail loads every quarantined file into one process.
 *
 * A pure subprocess check with no framework boot. Fixtures live under a per-run
 * `sys_get_temp_dir()` path, so it is parallel-safe by the guard's own rules.
 *
 * @command  php artisan test --compact tests/Feature/GlobalTestSymbolsCheckTest.php
 */
$root = dirname(__DIR__, 2);
$script = '.claude/skills/pest-testing/scripts/check-global-test-symbols.php';

/**
 * Write a fixture tree (filename => body, each prefixed with an open tag) and
 * return its directory.
 *
 * @param  array<string, string>  $files
 */
$writeSymbolFixtures = function (array $files): string {
    $dir = sys_get_temp_dir().'/global-symbols-'.bin2hex(random_bytes(6));
    mkdir($dir);

    foreach ($files as $name => $body) {
        file_put_contents($dir.'/'.$name, "<?php\n\n".mustBeString($body, 'fixture '.$name));
    }

    return $dir;
};

$runSymbolCheck = function (string $dir) use ($root, $script): Process {
    $process = new Process([PHP_BINARY, $script, $dir], $root);
    $process->run();

    return $process;
};

$cleanupSymbolFixtures = function (string $dir): void {
    foreach (glob($dir.'/*') ?: [] as $item) {
        @unlink($item);
    }
    @rmdir($dir);
};

it('fails on a defect the private guard used to report', function (array $files, string $reported) use ($writeSymbolFixtures, $runSymbolCheck, $cleanupSymbolFixtures) {
    $dir = $writeSymbolFixtures($files);

    try {
        $process = $runSymbolCheck($dir);

        expect($process->getExitCode())->toBe(1)
            ->and($process->getOutput())->toContain($reported);
    } finally {
        $cleanupSymbolFixtures($dir);
    }
})->with([
    'a global function declared in two files' => [
        [
            'ATest.php' => "function probe(): int\n{\n    return 1;\n}\n",
            'BTest.php' => "function probe(): int\n{\n    return 2;\n}\n",
        ],
        'Rule C  function probe',
    ],
    'a global const declared in two files' => [
        [
            'ATest.php' => "const SHARED = 1;\n",
            'BTest.php' => "const SHARED = 2;\n",
        ],
        'Rule C  const SHARED',
    ],
    'the same global const in two serial-quarantined files' => [
        [
            'ATest.php' => "uses()->group('serial');\n\nconst SERIAL = 1;\n",
            'BTest.php' => "uses()->group('serial');\n\nconst SERIAL = 2;\n",
        ],
        'Rule C  const SERIAL',
    ],
    'a single-segment use in a namespace-less file' => [
        ['ATest.php' => "use RuntimeException;\n\nit('throws', function () {\n    throw new RuntimeException('x');\n})->throws(RuntimeException::class);\n"],
        'Rule D',
    ],
]);

it('passes a tree with one declaration per name and no no-op import', function () use ($writeSymbolFixtures, $runSymbolCheck, $cleanupSymbolFixtures) {
    $dir = $writeSymbolFixtures([
        'ATest.php' => "function lonely(): int\n{\n    return 1;\n}\n",
        'BTest.php' => "namespace Probe\\Space;\n\nuse RuntimeException;\n\nfunction lonely(): RuntimeException\n{\n    return new RuntimeException('x');\n}\n",
    ]);

    try {
        $process = $runSymbolCheck($dir);

        expect($process->getExitCode())->toBe(0)
            ->and($process->getOutput())->toContain('read 2 file(s), 1 without a named namespace');
    } finally {
        $cleanupSymbolFixtures($dir);
    }
});

it('runs over tests in the CI job and the local-CI isolation job', function () use ($root, $script) {
    $workflow = mustNotBeFalse(file_get_contents($root.'/.github/workflows/ci.yml'), 'ci.yml');
    $runner = mustNotBeFalse(file_get_contents($root.'/scripts/local-ci.mjs'), 'local-ci.mjs');

    // The step sits inside the test-isolation-guard job, after the private guard.
    $job = mustBeString(strstr($workflow, "\n  test-isolation-guard:"), 'the test-isolation-guard job');
    $nextJob = preg_match('/\n  [a-z][a-z-]*:\n/', $job, $match, PREG_OFFSET_CAPTURE, 1) === 1 ? $match[0][1] : strlen($job);
    $job = substr($job, 0, $nextJob);

    // Split at the private guard's step, so the shared one must come after it.
    $afterGuard = explode('run: php scripts/check-parallel-safe-tests.php', $job, 2);

    expect($afterGuard)->toHaveCount(2)
        ->and($afterGuard[1] ?? '')->toContain('run: php '.$script.' tests')
        ->and($runner)->toContain("runPhp(['".$script."', 'tests'], worktreePath)");
});
