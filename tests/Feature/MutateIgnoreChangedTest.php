<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * Regression guard for `scripts/check-mutate-ignore-changed.php` (UAMS-Web/uams-statamic-archive#3256), the command behind
 * `composer mutate-ignore`, which runs the text check and the breadth check on a branch's
 * changed files and fails when either flags.
 *
 * The breadth arms reuse `MutateIgnoreBreadthTest`'s fixtures: the `faf7dac66` copy of
 * `ConditionalLogicEvaluator.php` carries an annotation that removes three mutants and that
 * the text check passes, and the `2ee8781cf` copy is clean for both. The text arm is a
 * finding only the text check makes, so neither half's exit code can stand in for the other.
 *
 * The git arms build a throwaway repository, so the changed-file listing is exercised
 * without depending on this checkout's state. Per-run temp paths, so it is parallel-safe.
 *
 * @command  php artisan test --compact tests/Feature/MutateIgnoreChangedTest.php
 */
$script = dirname(__DIR__, 2).'/scripts/check-mutate-ignore-changed.php';
$fixtures = dirname(__DIR__).'/Fixtures/MutateIgnoreBreadth';

/**
 * Run the command with the given arguments from the given directory.
 *
 * @param  array<int, string>  $arguments  Options and paths passed to the script.
 * @param  string|null  $cwd  The directory to run in; null for this checkout.
 * @return array{0: int|null, 1: string} The exit code and the combined output.
 */
$run = function (array $arguments, ?string $cwd = null) use ($script): array {
    // Narrow the list to strings so the process gets exactly what the CLI receives
    $command = [PHP_BINARY, $script];

    foreach ($arguments as $argument) {
        if (is_string($argument)) {
            $command[] = $argument;
        }
    }

    $process = new Process($command, $cwd);
    $process->setTimeout(300);
    $process->run();

    return [$process->getExitCode(), $process->getOutput().$process->getErrorOutput()];
};

/**
 * Run git in a directory, failing the test when it does not succeed.
 *
 * @param  string  $dir  The repository.
 * @param  array<int, string>  $arguments  Arguments after `git`.
 */
$git = function (string $dir, array $arguments): void {
    // A fixed identity, so a commit works on a machine with no git user configured
    $command = ['git', '-c', 'user.name=James Kirk', '-c', 'user.email=kirk@example.test'];

    foreach ($arguments as $argument) {
        if (is_string($argument)) {
            $command[] = $argument;
        }
    }

    $process = new Process($command, $dir);
    $process->run();

    expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
};

/**
 * Make a throwaway repository with one committed, clean file under `app/`.
 *
 * @return string The repository's path.
 */
$repository = function () use ($git, $fixtures): string {
    $dir = sys_get_temp_dir().'/mutate-ignore-changed-'.bin2hex(random_bytes(6));
    mkdir($dir.'/app', 0777, true);
    copy($fixtures.'/ConditionalLogicEvaluator.2ee8781cf.php.txt', $dir.'/app/Clean.php');

    $git($dir, ['init', '-q']);
    $git($dir, ['add', 'app/Clean.php']);
    $git($dir, ['commit', '-q', '-m', 'Clean file']);

    return $dir;
};

/**
 * Delete a directory and everything in it.
 *
 * @param  string  $dir  The directory.
 */
$remove = function (string $dir): void {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

    foreach ($files as $file) {
        if (! $file instanceof SplFileInfo) {
            continue;
        }

        // git marks its objects read-only, which stops unlink() on Windows
        chmod($file->getPathname(), 0777);
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }

    rmdir($dir);
};

it('fails on a file the breadth check flags and the text check passes', function () use ($run, $fixtures) {
    [$code, $output] = $run([$fixtures.'/ConditionalLogicEvaluator.faf7dac66.php.txt']);

    expect($code)->toBe(1)
        ->and($output)->toContain('text check exited 0, breadth check exited 1');
});

it('passes a file both checks pass', function () use ($run, $fixtures) {
    [$code, $output] = $run([$fixtures.'/ConditionalLogicEvaluator.2ee8781cf.php.txt']);

    expect($code)->toBe(0)
        ->and($output)->toContain('text check exited 0, breadth check exited 0');
});

it('fails on a file only the text check flags', function () use ($script) {
    // A bare marker with a trailing space, against a stand-in for an unpatched plugin. The
    // text check flags it as suppressing nothing, while the breadth check runs the installed
    // plugin, which trims the tail (UAMS-Web/uams-statamic-archive#2327), so the marker removes every mutant and passes.
    // Breadth stays clean, so the exit code can only come from the text check.
    $dir = sys_get_temp_dir().'/mutate-ignore-changed-text-'.bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);
    file_put_contents($dir.'/Fixture.php', "<?php\n\nfunction a(int \$n): bool\n{\n    // @pest-mutate-ignore \n    return \$n > 10;\n}\n");
    file_put_contents($dir.'/NodeVisitor.php', "<?php if (\$matches[1] === '') {}");

    $process = new Process([PHP_BINARY, $script, $dir.'/Fixture.php'], null, ['MUTATE_IGNORE_VISITOR_PATH' => $dir.'/NodeVisitor.php']);
    $process->setTimeout(300);
    $process->run();

    array_map('unlink', glob($dir.'/*') ?: []);
    rmdir($dir);

    expect($process->getExitCode())->toBe(1)
        ->and($process->getOutput())->toContain('text check exited 1, breadth check exited 0');
});

it('exits 2 when a check cannot run', function () use ($run) {
    [$code, $output] = $run([sys_get_temp_dir().'/mutate-ignore-changed-missing-'.bin2hex(random_bytes(6)).'.php']);

    expect($code)->toBe(2)
        ->and($output)->toContain('not a file or directory');
});

it('checks an untracked file under app/ when given no paths', function () use ($run, $repository, $remove, $fixtures) {
    $dir = $repository();
    copy($fixtures.'/ConditionalLogicEvaluator.faf7dac66.php.txt', $dir.'/app/Flagged.php');

    try {
        [$code, $output] = $run(['--base=HEAD'], $dir);
    } finally {
        $remove($dir);
    }

    expect($code)->toBe(1)
        ->and($output)->toContain('checking 1 file(s)')
        ->and($output)->toContain('Flagged.php:188');
});

it('checks a changed file whose name git would quote or a shell would rewrite', function (string $name) use ($run, $repository, $remove, $fixtures) {
    // git quotes a non-ASCII path unless asked for `-z` output, and on Windows
    // `escapeshellarg()` turns `%` into a space; either way the file was missed or misnamed
    $dir = $repository();
    copy($fixtures.'/ConditionalLogicEvaluator.faf7dac66.php.txt', $dir.'/app/'.$name);

    try {
        [$code, $output] = $run(['--base=HEAD'], $dir);
    } finally {
        $remove($dir);
    }

    expect($code)->toBe(1)
        ->and($output)->toContain($name.':188');
})->with([
    'non-ASCII' => ['café.php'],
    'percent signs' => ['P%PATH%x.php'],
]);

it('lists the same files when run from a subdirectory', function () use ($run, $repository, $remove, $fixtures) {
    $dir = $repository();
    copy($fixtures.'/ConditionalLogicEvaluator.faf7dac66.php.txt', $dir.'/app/Flagged.php');

    try {
        [$code, $output] = $run(['--base=HEAD'], $dir.'/app');
    } finally {
        $remove($dir);
    }

    expect($code)->toBe(1)
        ->and($output)->toContain('Flagged.php:188');
});

it('checks a committed change on the branch when given no paths', function () use ($run, $repository, $remove, $git, $fixtures) {
    $dir = $repository();
    $git($dir, ['branch', 'base']);
    copy($fixtures.'/ConditionalLogicEvaluator.faf7dac66.php.txt', $dir.'/app/Clean.php');
    $git($dir, ['commit', '-q', '-am', 'Widen an annotation']);

    try {
        [$code, $output] = $run(['--base=base'], $dir);
    } finally {
        $remove($dir);
    }

    expect($code)->toBe(1)
        ->and($output)->toContain('Clean.php:188');
});

it('passes when nothing under app/ or tests/ changed', function () use ($run, $repository, $remove) {
    $dir = $repository();
    file_put_contents($dir.'/notes.php', "<?php\n// @pest-mutate-ignore: not a mutator\n");

    try {
        [$code, $output] = $run(['--base=HEAD'], $dir);
    } finally {
        $remove($dir);
    }

    expect($code)->toBe(0)
        ->and($output)->toContain('no changed PHP files under app/ or tests/');
});

it('exits 2 when git cannot resolve the base', function () use ($run, $repository, $remove) {
    $dir = $repository();

    try {
        [$code, $output] = $run(['--base=no-such-ref'], $dir);
    } finally {
        $remove($dir);
    }

    expect($code)->toBe(2)
        ->and($output)->toContain('git merge-base');
});
