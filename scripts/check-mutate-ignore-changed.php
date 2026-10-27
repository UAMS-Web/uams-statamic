<?php

declare(strict_types=1);

/**
 * Run both `@pest-mutate-ignore` checks on the files a branch changed (UAMS-Web/uams-statamic-archive#3256).
 *
 * `scripts/check-mutate-ignore.php` reads each marker's text and
 * `scripts/check-mutate-ignore-breadth.php` counts the mutants each annotation removes.
 * Only local CI's `encoding` job ran the second, so a pull request whose annotation removed
 * two mutants passed the text check its author ran, then failed review on breadth: UAMS-Web/uams-statamic-archive#3251
 * and UAMS-Web/uams-statamic-archive#3255 were handed back for exactly that on 2026-10-23. This runs both, on the same
 * files, the way the `encoding` job runs them (the breadth check with its committed
 * baseline), and is what `composer mutate-ignore` calls.
 *
 * WHICH FILES. With paths, those paths. Without, every `*.php` file under `app/` or `tests/`
 * that differs from the merge base of `HEAD` and `--base` (default `origin/main`),
 * committed or not, plus untracked files git does not ignore. Deleted files are skipped.
 * The listing is read from the repository root wherever the command is run from.
 *
 * BOUND. Scoped to changed files, a clean run says nothing about files the branch did not
 * touch; the `encoding` job still checks the whole of `app` and `tests`. The breadth check
 * fails a stale baseline entry only for a file it scanned, so scoping cannot raise a
 * stale-entry failure on its own.
 *
 * Usage:  php scripts/check-mutate-ignore-changed.php [--base=<ref>] [path ...]
 * Exit:   0 = both checks clean, or nothing to check; 1 = either check flagged;
 *         2 = either check could not run or exited with any other code, or git could
 *         not list the changed files.
 */

/**
 * Stop with exit code 2, which every caller reads as "could not run" rather than clean.
 *
 * @param  string  $message  What prevented the run.
 */
function changedFail(string $message): never
{
    fwrite(STDERR, "check-mutate-ignore-changed: {$message}\n");

    exit(2);
}

/**
 * Run a command without a shell and return its exit code and its output.
 *
 * Without a shell because PHP's `escapeshellarg()` on Windows replaces `%`, `!` and `"` with
 * spaces, which turned a file named `P%PATH%x.php` into a path that does not exist. Only one
 * pipe is read, so neither stream can fill and block: standard error is either folded into
 * standard output, or written to a temp file and returned separately, which git's NUL-separated
 * listing needs, since a warning folded into it would split an entry and drop a file.
 *
 * @param  array<int, string>  $command  The program and its arguments.
 * @param  bool  $separateErrors  Whether to return standard error apart from standard output.
 * @return array{0: int, 1: string, 2: string} The exit code, the output, and standard error
 *                                             when it was kept apart.
 */
function changedExec(array $command, bool $separateErrors = false): array
{
    $errorFile = $separateErrors ? tempnam(sys_get_temp_dir(), 'mutate-ignore-changed-') : false;
    $descriptors = [1 => ['pipe', 'w'], 2 => $errorFile === false ? ['redirect', 1] : ['file', $errorFile, 'w']];
    $process = proc_open($command, $descriptors, $pipes);

    if (! is_resource($process)) {
        changedFail('could not start '.$command[0]);
    }

    $output = (string) stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $code = proc_close($process);

    $errors = '';
    if ($errorFile !== false) {
        $errors = (string) file_get_contents($errorFile);
        unlink($errorFile);
    }

    return [$code, $output, $errors];
}

/**
 * Run a git command and return its NUL-separated output entries.
 *
 * `-z` output is read rather than lines, because git otherwise quotes a path holding a
 * non-ASCII byte (`core.quotePath`), and the quoted form names no file on disk.
 *
 * @param  array<int, string>  $arguments  Arguments after `git`.
 * @return array<int, string> The non-empty entries.
 */
function changedGit(array $arguments): array
{
    [$code, $stdout, $stderr] = changedExec(['git', ...$arguments], true);

    if ($code !== 0) {
        changedFail('`git '.implode(' ', $arguments).'` exited '.$code.': '.trim($stderr));
    }

    return array_values(array_filter(explode("\0", $stdout), static fn (string $entry): bool => trim($entry) !== ''));
}

/**
 * Run one of the two checks and return its exit code, printing its output.
 *
 * The output is captured and printed here rather than inherited, because a child writing to
 * an inherited standard output on Windows lost a line this script had printed before it.
 *
 * @param  string  $script  The check's path.
 * @param  array<int, string>  $arguments  Options and paths for the check.
 */
function changedRun(string $script, array $arguments): int
{
    [$code, $output] = changedExec([PHP_BINARY, $script, ...$arguments]);
    echo $output;

    return $code;
}

$base = 'origin/main';
$paths = [];

foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--base=')) {
        $base = substr($argument, strlen('--base='));
    } elseif (str_starts_with($argument, '--')) {
        changedFail("unknown option {$argument}");
    } else {
        $paths[] = $argument;
    }
}

if ($paths === []) {
    // Paths come back relative to the repository root, and the `:(top)` path filters are read
    // from it too, so a run from a subdirectory lists the same files as one from the root
    $root = trim(changedGit(['rev-parse', '--show-toplevel'])[0] ?? '');

    // The merge base, so a branch behind `main` does not report main's own changes as its own
    $mergeBase = trim(changedGit(['merge-base', 'HEAD', $base])[0] ?? '');
    $listed = [
        ...changedGit(['diff', '-z', '--name-only', '--diff-filter=d', $mergeBase, '--', ':(top)app', ':(top)tests']),
        ...changedGit(['ls-files', '-z', '--full-name', '--others', '--exclude-standard', '--', ':(top)app', ':(top)tests']),
    ];

    foreach (array_unique($listed) as $path) {
        if (str_ends_with($path, '.php') && is_file($root.'/'.$path)) {
            $paths[] = $root.'/'.$path;
        }
    }

    if ($paths === []) {
        echo "check-mutate-ignore-changed: no changed PHP files under app/ or tests/ against {$base}\n";
        exit(0);
    }
}

echo 'check-mutate-ignore-changed: checking '.count($paths)." file(s)\n";

$text = changedRun(__DIR__.'/check-mutate-ignore.php', $paths);
$breadth = changedRun(__DIR__.'/check-mutate-ignore-breadth.php', [
    '--baseline='.__DIR__.'/check-mutate-ignore-breadth.baseline.json',
    ...$paths,
]);

echo "check-mutate-ignore-changed: text check exited {$text}, breadth check exited {$breadth}\n";

// Anything but 0 or 1, a fatal error's 255 included, means a check did not finish
if (! in_array($text, [0, 1], true) || ! in_array($breadth, [0, 1], true)) {
    exit(2);
}

exit($text === 0 && $breadth === 0 ? 0 : 1);
