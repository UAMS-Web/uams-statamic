<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * Pins `scripts/setup-git-hooks.php`, the code-quality skill's installer, inside
 * and outside a git work tree (UAMS-Web/uams-statamic-archive#3085).
 *
 * Composer runs it on every install and update, so each refusal has to be a quiet
 * exit 0 and the one write it makes has to be `core.hooksPath = .githooks`. Each
 * case copies the real script
 * into a throwaway project and runs it there as Composer would, because the
 * installer configures the repository that holds its own `scripts/` directory.
 *
 * Global and system git config are blanked, so a developer's own `core.hooksPath`
 * cannot leak into a fixture, and `GITHUB_ACTIONS` is removed except in the case
 * that sets it, so the suite answers the same under remote CI.
 *
 * @command  php artisan test --compact tests/Feature/SetupGitHooksTest.php
 */
$script = dirname(__DIR__, 2).'/scripts/setup-git-hooks.php';

/**
 * The environment every fixture command runs under. `false` removes a variable.
 *
 * @var array<string, string|false>
 */
$fixtureEnv = [
    'GIT_CONFIG_GLOBAL' => '/dev/null',
    'GIT_CONFIG_SYSTEM' => '/dev/null',
    'GITHUB_ACTIONS' => false,
];

/**
 * The same environment as the runner of a GitHub Actions job sees it.
 *
 * @var array<string, string|false>
 */
$actionsEnv = ['GITHUB_ACTIONS' => 'true'] + $fixtureEnv;

/**
 * Run a command in a fixture directory and return its exit code, stdout and stderr, trimmed.
 *
 * @param  string  ...$command  The argv to run.
 * @return array{0: int, 1: string, 2: string}
 */
$run = function (string $cwd, bool $underActions, string ...$command) use ($fixtureEnv, $actionsEnv): array {
    $process = new Process($command, $cwd, $underActions ? $actionsEnv : $fixtureEnv);
    $process->run();

    return [$process->getExitCode() ?? 1, trim($process->getOutput()), trim($process->getErrorOutput())];
};

/**
 * Create a directory holding a copy of the installer at `scripts/setup-git-hooks.php`,
 * optionally as a git repository whose `.githooks/pre-commit` is tracked.
 */
$project = function (string $dir, bool $repository = true, bool $trackHook = true) use ($script, $run): string {
    mkdir($dir.'/scripts', 0777, true);
    copy($script, $dir.'/scripts/setup-git-hooks.php');

    if (! $repository) {
        return $dir;
    }

    $run($dir, false, 'git', 'init', '--initial-branch=main');
    mkdir($dir.'/.githooks');
    file_put_contents($dir.'/.githooks/pre-commit', "#!/bin/sh\nexit 0\n");

    if ($trackHook) {
        $run($dir, false, 'git', 'add', '.githooks/pre-commit');
    }

    return $dir;
};

/**
 * Run the copied installer in a project, as Composer's script hook does.
 *
 * @return array{0: int, 1: string, 2: string}
 */
$install = fn (string $dir, bool $underActions = false): array => $run($dir, $underActions, PHP_BINARY, 'scripts/setup-git-hooks.php');

/** The project's own `core.hooksPath`, or '' when unset. */
$hooksPath = fn (string $dir): string => $run($dir, false, 'git', 'config', '--local', '--get', 'core.hooksPath')[1];

/** A fresh, unique fixture root under the system temp directory. */
$tempRoot = fn (): string => sys_get_temp_dir().'/setup-git-hooks-'.bin2hex(random_bytes(6));

/**
 * Recursively delete a fixture directory, tolerating read-only git objects.
 */
$cleanup = function (string $dir): void {
    $remove = function (string $path) use (&$remove): void {
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $item = $path.DIRECTORY_SEPARATOR.$entry;

            if (is_dir($item)) {
                $remove($item);

                continue;
            }

            @chmod($item, 0666);
            @unlink($item);
        }

        @rmdir($path);
    };

    $remove($dir);
};

it('points core.hooksPath at .githooks in a work tree that tracks the hook, then stays quiet', function () use ($project, $install, $hooksPath, $tempRoot, $cleanup): void {
    $dir = $project($tempRoot());

    try {
        [$exitCode, $stdout] = $install($dir);

        expect($exitCode)->toBe(0)
            ->and($stdout)->toBe('Git hooks installed (core.hooksPath -> .githooks).')
            ->and($hooksPath($dir))->toBe('.githooks');

        [$againExit, $againStdout] = $install($dir);

        expect($againExit)->toBe(0)
            ->and($againStdout)->toBe('')
            ->and($hooksPath($dir))->toBe('.githooks');
    } finally {
        $cleanup($dir);
    }
});

it('names the core.hooksPath value it replaces', function () use ($project, $install, $hooksPath, $run, $tempRoot, $cleanup): void {
    $dir = $project($tempRoot());

    try {
        $run($dir, false, 'git', 'config', 'core.hooksPath', '.husky');

        [$exitCode, $stdout] = $install($dir);

        expect($exitCode)->toBe(0)
            ->and($stdout)->toBe('Git hooks installed (core.hooksPath -> .githooks) (it was .husky).')
            ->and($hooksPath($dir))->toBe('.githooks');
    } finally {
        $cleanup($dir);
    }
});

it('does nothing outside a git work tree', function () use ($project, $install, $tempRoot, $cleanup): void {
    $dir = $project($tempRoot(), repository: false);

    try {
        [$exitCode, $stdout, $stderr] = $install($dir);

        expect($exitCode)->toBe(0)
            ->and($stdout)->toBe('')
            ->and($stderr)->toBe('')
            ->and(is_dir($dir.'/.git'))->toBeFalse();
    } finally {
        $cleanup($dir);
    }
});

it('does nothing when the project is a subdirectory of another repository', function () use ($project, $install, $hooksPath, $run, $tempRoot, $cleanup): void {
    // The outer repository tracks a hook of its own, so only the subdirectory check stops the write.
    $outer = $project($tempRoot());
    $inner = $outer.'/packages/khan';

    try {
        mkdir($inner, 0777, true);
        mkdir($inner.'/scripts');
        copy($outer.'/scripts/setup-git-hooks.php', $inner.'/scripts/setup-git-hooks.php');
        mkdir($inner.'/.githooks');
        file_put_contents($inner.'/.githooks/pre-commit', "#!/bin/sh\nexit 0\n");
        $run($outer, false, 'git', 'add', 'packages/khan/.githooks/pre-commit');

        [$exitCode, $stdout] = $install($inner);

        expect($exitCode)->toBe(0)
            ->and($stdout)->toBe('')
            ->and($hooksPath($outer))->toBe('');
    } finally {
        $cleanup($outer);
    }
});

it('does nothing when .githooks/pre-commit is not tracked', function () use ($project, $install, $hooksPath, $tempRoot, $cleanup): void {
    $dir = $project($tempRoot(), trackHook: false);

    try {
        [$exitCode, $stdout] = $install($dir);

        expect($exitCode)->toBe(0)
            ->and($stdout)->toBe('')
            ->and($hooksPath($dir))->toBe('');
    } finally {
        $cleanup($dir);
    }
});

it('does nothing under GitHub Actions', function () use ($project, $install, $hooksPath, $tempRoot, $cleanup): void {
    $dir = $project($tempRoot());

    try {
        [$exitCode, $stdout] = $install($dir, underActions: true);

        expect($exitCode)->toBe(0)
            ->and($stdout)->toBe('')
            ->and($hooksPath($dir))->toBe('');
    } finally {
        $cleanup($dir);
    }
});
