<?php

declare(strict_types=1);

/**
 * Run one `gh api` call, retrying a bounded number of times when the response is unusable (#1510).
 *
 * The `Choose shard timings` step in `.github/workflows/ci.yml` makes two `gh api` calls
 * under `bash -e -o pipefail`. On run 45466456000 (#1266) one of them got an empty body,
 * `gh` printed `unexpected end of JSON input`, and the Windows shard failed before Pest
 * started, while the other shards' identical calls succeeded. This wrapper retries such
 * a transient instead of letting it fail the job.
 *
 * Usage:
 *   php scripts/gh-api-retry.php [--attempts=N] [--delay=SECONDS] [--json] -- <gh api arguments>
 *
 * A response is usable when `gh` exits 0 and prints something other than whitespace or
 * `null`; with `--json` it must also decode as JSON, which catches a truncated body that
 * `gh` passed through with exit 0. On a usable response the wrapper prints `gh`'s output
 * unchanged and exits 0. Otherwise it waits `delay * attempt` seconds and tries again, up
 * to `--attempts` tries in all (default 4, delay default 5). When the tries run out it
 * names the call, the last exit code and the last output on standard error, and exits 1.
 * A usage error exits 2.
 *
 * `GH_API_RETRY_COMMAND`, a JSON array, replaces the `["gh", "api"]` the arguments are
 * appended to. It exists so `tests/Feature/GhApiRetryTest.php` can stand in a fake `gh`.
 */

/**
 * Print a message to standard error and exit with the given code.
 */
function ghApiRetryFail(string $message, int $code): never
{
    fwrite(STDERR, 'gh-api-retry: '.$message.PHP_EOL);

    exit($code);
}

/**
 * Shorten captured output for a message, keeping it on one line.
 */
function ghApiRetryExcerpt(string $output): string
{
    $flat = trim((string) preg_replace('/\s+/', ' ', $output));

    if ($flat === '') {
        return '(empty)';
    }

    return strlen($flat) > 300 ? substr($flat, 0, 300).'...' : $flat;
}

/**
 * Why a response is unusable, or null when it is usable.
 */
function ghApiRetryProblem(int $exitCode, string $stdout, bool $requireJson): ?string
{
    if ($exitCode !== 0) {
        return "exit {$exitCode}";
    }

    $trimmed = trim($stdout);

    if ($trimmed === '' || $trimmed === 'null') {
        return 'empty response';
    }

    if ($requireJson) {
        json_decode($stdout);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return 'response is not JSON ('.json_last_error_msg().')';
        }
    }

    return null;
}

$arguments = $_SERVER['argv'];
$separator = array_search('--', $arguments, true);

if ($separator === false || $separator === count($arguments) - 1) {
    ghApiRetryFail('usage: [--attempts=N] [--delay=SECONDS] [--json] -- <gh api arguments>', 2);
}

$ghArguments = array_slice($arguments, $separator + 1);
$attempts = 4;
$delay = 5;
$requireJson = false;

foreach (array_slice($arguments, 1, $separator - 1) as $option) {
    if ($option === '--json') {
        $requireJson = true;
    } elseif (preg_match('/^--attempts=([1-9]\d*)$/', $option, $match) === 1) {
        $attempts = (int) $match[1];
    } elseif (preg_match('/^--delay=(\d+)$/', $option, $match) === 1) {
        $delay = (int) $match[1];
    } else {
        ghApiRetryFail("unknown option {$option}", 2);
    }
}

$prefix = ['gh', 'api'];
$override = getenv('GH_API_RETRY_COMMAND');

if (is_string($override) && $override !== '') {
    $decoded = json_decode($override, true);

    if (! is_array($decoded) || $decoded === [] || ! array_is_list($decoded) || array_filter($decoded, is_string(...)) !== $decoded) {
        ghApiRetryFail('GH_API_RETRY_COMMAND must be a JSON array of strings', 2);
    }

    $prefix = $decoded;
}

$call = 'gh api '.implode(' ', $ghArguments);
$stdout = '';
$stderr = '';
$problem = null;

for ($attempt = 1; $attempt <= $attempts; $attempt++) {
    $process = proc_open([...$prefix, ...$ghArguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

    if (! is_resource($process)) {
        ghApiRetryFail("could not start {$call}", 1);
    }

    $stdout = (string) stream_get_contents($pipes[1]);
    $stderr = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    $problem = ghApiRetryProblem($exitCode, $stdout, $requireJson);

    if ($problem === null) {
        fwrite(STDOUT, $stdout);

        exit(0);
    }

    if ($attempt < $attempts) {
        $wait = $delay * $attempt;
        fwrite(STDERR, "gh-api-retry: {$call}: attempt {$attempt} of {$attempts} failed ({$problem}); retrying in {$wait}s".PHP_EOL);
        sleep($wait);
    }
}

ghApiRetryFail(
    "{$call} failed after {$attempts} attempt(s): {$problem}; last stdout: ".ghApiRetryExcerpt($stdout).'; last stderr: '.ghApiRetryExcerpt($stderr),
    1,
);
