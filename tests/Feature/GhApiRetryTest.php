<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * Regression guard for `scripts/gh-api-retry.php` (#1510), the wrapper the `Choose shard
 * timings` step in ci.yml runs its two `gh api` calls through. On run 45466456000 one of
 * those calls got an empty body and failed a Windows shard before Pest started.
 *
 * A fake `gh`, written to a per-test temporary directory and named through
 * `GH_API_RETRY_COMMAND`, plays a scripted list of responses, one per call, and records
 * the arguments of each call. Driven as a subprocess, like `SelectShardTimingsTest`, so
 * the CLI contract ci.yml depends on is what is pinned. Framework-free, with fixtures
 * under a per-run `sys_get_temp_dir()` path, so it is parallel-safe.
 *
 * @command  php artisan test --compact tests/Feature/GhApiRetryTest.php
 */
$script = dirname(__DIR__, 2).'/scripts/gh-api-retry.php';

$fakeGh = <<<'PHP'
<?php
$dir = getenv('FAKE_GH_DIR');
$plan = json_decode((string) file_get_contents($dir.'/plan.json'), true);
$calls = is_file($dir.'/calls.json') ? json_decode((string) file_get_contents($dir.'/calls.json'), true) : [];
$response = $plan[min(count($calls), count($plan) - 1)];
$calls[] = array_slice($argv, 1);
file_put_contents($dir.'/calls.json', json_encode($calls));
fwrite(STDOUT, $response['stdout']);
fwrite(STDERR, $response['stderr'] ?? '');
exit($response['exit'] ?? 0);
PHP;

$run =
    /**
     * Run the wrapper against a fake `gh` that answers each call with the next response.
     *
     * @param  list<array{stdout: string, stderr?: string, exit?: int}>  $responses  One per call; the last repeats
     * @param  string  ...$options  Wrapper options before `--`
     * @return array{process: Process, calls: mixed}
     */
    function (array $responses, string ...$options) use ($script, $fakeGh): array {
        $dir = sys_get_temp_dir().'/gh-api-retry-'.bin2hex(random_bytes(6));
        mkdir($dir);
        file_put_contents($dir.'/gh.php', $fakeGh);
        file_put_contents($dir.'/plan.json', (string) json_encode($responses));

        try {
            $process = new Process(
                [PHP_BINARY, $script, '--delay=0', ...$options, '--', 'repos/o/r/actions/runs/1/attempts/1', '--jq', '.run_started_at'],
                null,
                ['GH_API_RETRY_COMMAND' => (string) json_encode([PHP_BINARY, $dir.'/gh.php']), 'FAKE_GH_DIR' => $dir],
            );
            $process->run();
            $calls = is_file($dir.'/calls.json') ? json_decode((string) file_get_contents($dir.'/calls.json'), true) : [];
        } finally {
            array_map(unlink(...), glob($dir.'/*') ?: []);
            rmdir($dir);
        }

        return ['process' => $process, 'calls' => $calls];
    };

it('prints the first usable response unchanged and calls gh once', function () use ($run): void {
    $result = $run([['stdout' => "2026-11-24T19:09:00Z\n"]]);

    expect($result['process']->getExitCode())->toBe(0)
        ->and($result['process']->getOutput())->toBe("2026-11-24T19:09:00Z\n")
        ->and($result['calls'])->toBe([['repos/o/r/actions/runs/1/attempts/1', '--jq', '.run_started_at']]);
});

it('retries an empty response and prints the next usable one', function () use ($run): void {
    $result = $run([
        ['stdout' => '', 'stderr' => 'unexpected end of JSON input', 'exit' => 1],
        ['stdout' => "\n"],
        ['stdout' => "null\n"],
        ['stdout' => "2026-11-24T19:09:00Z\n"],
    ]);

    expect($result['process']->getExitCode())->toBe(0)
        ->and($result['process']->getOutput())->toBe("2026-11-24T19:09:00Z\n")
        ->and($result['calls'])->toHaveCount(4)
        ->and($result['process']->getErrorOutput())->toContain('attempt 1 of 4 failed (exit 1)', 'attempt 2 of 4 failed (empty response)', 'attempt 3 of 4 failed (empty response)');
});

it('retries a truncated JSON body when --json is given', function () use ($run): void {
    $listing = '{"total_count":1,"actions_caches":[{"key":"pest-shard-timings-1"}]}';
    $result = $run([['stdout' => '{"total_count":1,"actions_ca'], ['stdout' => $listing]], '--json');

    expect($result['process']->getExitCode())->toBe(0)
        ->and($result['process']->getOutput())->toBe($listing)
        ->and($result['calls'])->toHaveCount(2)
        ->and($result['process']->getErrorOutput())->toContain('response is not JSON');
});

it('accepts a truncated body without --json, as the timestamp call needs no JSON', function () use ($run): void {
    $result = $run([['stdout' => '{"total_count":1,"actions_ca']]);

    expect($result['process']->getExitCode())->toBe(0)
        ->and($result['calls'])->toHaveCount(1);
});

it('fails naming the call and its last output when every attempt fails', function () use ($run): void {
    $result = $run([['stdout' => '', 'stderr' => 'HTTP 502: Bad Gateway', 'exit' => 1]], '--attempts=3');

    expect($result['process']->getExitCode())->toBe(1)
        ->and($result['process']->getOutput())->toBe('')
        ->and($result['calls'])->toHaveCount(3)
        ->and($result['process']->getErrorOutput())->toContain(
            'gh api repos/o/r/actions/runs/1/attempts/1 --jq .run_started_at failed after 3 attempt(s): exit 1',
            'last stderr: HTTP 502: Bad Gateway',
        );
});

it('refuses a call with no gh arguments or an unknown option', function (string ...$arguments) use ($script): void {
    $process = new Process([PHP_BINARY, $script, ...$arguments]);
    $process->run();

    expect($process->getExitCode())->toBe(2)
        ->and($process->getErrorOutput())->toContain('gh-api-retry:');
})->with([
    'no separator' => ['repos/o/r'],
    'nothing after the separator' => ['--'],
    'unknown option' => ['--retries=3', '--', 'repos/o/r'],
    'zero attempts' => ['--attempts=0', '--', 'repos/o/r'],
]);
