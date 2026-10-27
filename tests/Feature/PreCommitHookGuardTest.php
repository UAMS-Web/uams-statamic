<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * Regression guard for `.githooks/pre-commit` (UAMS-Web/uams-statamic-archive#1850, and the UAMS-Web/uams-statamic-archive#1131 parity gate it
 * already carried). Each case builds a throwaway git repository, drives the real
 * hook against it, and asserts on the exit code and the names it prints.
 *
 * The merge gate exists because a merge commit records the **index**: a file you
 * edited while resolving but never `git add`ed is dropped from the commit, while
 * `composer stan`, Pest and Pint all keep passing because they read the **working
 * tree**. Resolving UAMS-Web/uams-statamic-archive#1820 into PR UAMS-Web/uams-statamic-archive#1794 hit exactly that — a ported hunk in
 * `tests/Support/SchemaLocationFixtures.php` never reached the commit, and `main`
 * would have taken two errors from a branch whose own checks were green.
 *
 * The states the gate has to separate are each pinned below, plus the
 * both-gates-trip case: a hook that `exit 1`s from the first gate would never
 * reach the second, so the two would silently stop composing.
 *
 * Driven as a subprocess (like `tests/Feature/ParallelSafeTestsGuardTest.php` and
 * `SkipBaselineGuardTest`) rather than by reading the script: that pins the real
 * contract — exit code and printed names — which is what git actually depends on.
 * Pure subprocess check; framework-free, and its fixtures live under a per-run
 * `sys_get_temp_dir()` path, so it is parallel-safe.
 *
 * @command  php artisan test --compact tests/Feature/PreCommitHookGuardTest.php
 */
$root = dirname(__DIR__, 2);
$hook = $root.'/.githooks/pre-commit';

/**
 * The environment every fixture git call runs under.
 *
 * Blanking the global and system config keeps the fixtures free of the
 * developer's own settings — most importantly `core.hooksPath`, which would
 * otherwise fire this very hook during fixture setup. Identity is set per repo
 * with `git config` rather than through `GIT_AUTHOR_*`/`GIT_COMMITTER_*` so it
 * survives into subprocesses this test does not build itself: without it
 * `git merge` aborts on an unknown committer *before* it can conflict, leaving no
 * `MERGE_HEAD` and a fixture that silently tests nothing.
 *
 * @var array<string, string>
 */
$fixtureEnv = [
    'GIT_CONFIG_GLOBAL' => '/dev/null',
    'GIT_CONFIG_SYSTEM' => '/dev/null',
];

/**
 * Run a command in a fixture repository, asserting it succeeded.
 *
 * Variadic so the argv is natively `list<string>` — a docblock-only type here
 * would leave `Process` and `implode()` taking a plain `array` at level 10.
 *
 * @param  string  $cwd  Working directory for the command.
 * @param  string  ...$command  The argv to run.
 */
$git = function (string $cwd, string ...$command) use ($fixtureEnv): void {
    $process = new Process($command, $cwd, $fixtureEnv);
    $process->run();

    if (! $process->isSuccessful()) {
        throw new RuntimeException(sprintf(
            'Fixture setup failed: %s => %s%s',
            implode(' ', $command),
            $process->getOutput(),
            $process->getErrorOutput(),
        ));
    }
};

/**
 * Create a git repository with one commit on `main`, and return its path.
 */
$newRepo = function () use ($git): string {
    $dir = sys_get_temp_dir().'/hook-guard-'.bin2hex(random_bytes(6));
    mkdir($dir);

    $git($dir, 'git', 'init', '--initial-branch=main');
    $git($dir, 'git', 'config', 'user.name', 'Nyota Uhura');
    $git($dir, 'git', 'config', 'user.email', 'uhura@example.test');

    file_put_contents($dir.'/shared.txt', "base\n");
    $git($dir, 'git', 'add', 'shared.txt');
    $git($dir, 'git', 'commit', '-m', 'base');

    return $dir;
};

/**
 * Drive the hook itself, exactly as git would: run it with the repo as cwd.
 *
 * @return array{0: int, 1: string} The exit code and combined output.
 */
$runHook = function (string $dir) use ($hook, $fixtureEnv): array {
    $process = new Process(['sh', $hook], $dir, $fixtureEnv);
    $process->run();

    return [$process->getExitCode() ?? 1, $process->getOutput().$process->getErrorOutput()];
};

/**
 * Leave the fixture repo mid-merge with a resolution present on disk but NOT
 * staged — the state UAMS-Web/uams-statamic-archive#1850 was observed in.
 *
 * `other.txt` is the load-bearing file: it auto-merges cleanly and is then
 * hand-edited, which is what actually happened on UAMS-Web/uams-statamic-archive#1820 and what git itself does
 * not catch. A file left genuinely *unmerged* is already refused by
 * `git commit` ("Committing is not possible because you have unmerged files"), so
 * that case needs no gate. `shared.txt` conflicts only to stop the merge and
 * leave `MERGE_HEAD` set.
 */
$midMerge = function (string $dir) use ($git, $fixtureEnv): void {
    file_put_contents($dir.'/other.txt', "base\n");
    $git($dir, 'git', 'add', 'other.txt');
    $git($dir, 'git', 'commit', '-m', 'add other');

    $git($dir, 'git', 'checkout', '-b', 'topic');
    file_put_contents($dir.'/shared.txt', "topic\n");
    file_put_contents($dir.'/other.txt', "base\ntopic addition\n");
    $git($dir, 'git', 'commit', '-am', 'topic edit');

    $git($dir, 'git', 'checkout', 'main');
    file_put_contents($dir.'/shared.txt', "main\n");
    $git($dir, 'git', 'commit', '-am', 'main edit');

    // Expected to exit non-zero: it conflicts, which is the point.
    $merge = new Process(['git', 'merge', 'topic'], $dir, $fixtureEnv);
    $merge->run();

    // Resolve the conflict and stage it, so the repo is committable...
    file_put_contents($dir.'/shared.txt', "resolved\n");
    $git($dir, 'git', 'add', 'shared.txt');

    // ...then hand-edit the cleanly-merged file and deliberately leave it unstaged.
    file_put_contents($dir.'/other.txt', "base\ntopic addition\nported hunk\n");
};

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

it('blocks a merge commit whose resolution is unstaged, naming the file', function () use ($newRepo, $midMerge, $runHook, $cleanup): void {
    $dir = $newRepo();

    try {
        $midMerge($dir);

        [$exitCode, $output] = $runHook($dir);

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('other.txt')
            // The message has to explain *why*, or the fix looks like `--no-verify`.
            ->and($output)->toContain('records the index');
    } finally {
        $cleanup($dir);
    }
});

it('allows a merge commit once every resolution is staged', function () use ($newRepo, $midMerge, $runHook, $git, $cleanup): void {
    $dir = $newRepo();

    try {
        $midMerge($dir);
        $git($dir, 'git', 'add', 'other.txt');

        [$exitCode] = $runHook($dir);

        expect($exitCode)->toBe(0);
    } finally {
        $cleanup($dir);
    }
});

it('leaves an ordinary commit with unstaged changes alone', function () use ($newRepo, $runHook, $git, $cleanup): void {
    $dir = $newRepo();

    try {
        // The everyday case: something staged, something else dirty, no merge.
        file_put_contents($dir.'/staged.txt', "new\n");
        $git($dir, 'git', 'add', 'staged.txt');
        file_put_contents($dir.'/shared.txt', "dirty\n");

        [$exitCode, $output] = $runHook($dir);

        expect($exitCode)->toBe(0)
            ->and($output)->not->toContain('Merge resolution gate');
    } finally {
        $cleanup($dir);
    }
});

it('ignores a submodule whose pointer moved under an otherwise clean merge', function () use ($newRepo, $midMerge, $runHook, $git, $fixtureEnv, $cleanup): void {
    $dir = $newRepo();

    try {
        // A submodule with two commits, checked out one behind its tip.
        $subDir = $dir.'-sub';
        mkdir($subDir);
        $git($subDir, 'git', 'init', '--initial-branch=main');
        $git($subDir, 'git', 'config', 'user.name', 'Montgomery Scott');
        $git($subDir, 'git', 'config', 'user.email', 'scott@example.test');
        file_put_contents($subDir.'/s.txt', "one\n");
        $git($subDir, 'git', 'add', 's.txt');
        $git($subDir, 'git', 'commit', '-m', 'one');
        file_put_contents($subDir.'/s.txt', "two\n");
        $git($subDir, 'git', 'commit', '-am', 'two');

        $tip = new Process(['git', 'rev-parse', 'HEAD'], $subDir, $fixtureEnv);
        $tip->run();
        $subTip = trim($tip->getOutput());

        // Record the pointer one commit BEHIND the tip, so moving to the tip
        // below is a real pointer difference. Adding it at the tip and then
        // "moving" to the same commit changes nothing and the case passes
        // vacuously — caught by mutation-verifying this very test.
        $git($subDir, 'git', 'checkout', 'HEAD~1');

        $git($dir, 'git', '-c', 'protocol.file.allow=always', 'submodule', 'add', $subDir, 'sub');
        // Mirror this repo: `content` carries `ignore = dirty` in .gitmodules.
        $git($dir, 'git', 'config', '-f', '.gitmodules', 'submodule.sub.ignore', 'dirty');
        $git($dir, 'git', 'add', '-A');
        $git($dir, 'git', 'commit', '-m', 'add submodule');

        $midMerge($dir);
        $git($dir, 'git', 'add', 'other.txt');

        // Now move the submodule HEAD, as a concurrent session sharing
        // .git/modules/<name> across linked worktrees does. `ignore = dirty`
        // does NOT hide a pointer difference, so a bare `git diff --name-only`
        // lists `sub` — and the gate's "stage them" advice would commit the
        // other session's revision into this branch.
        $git($dir, 'git', '-C', 'sub', 'checkout', $subTip);

        [$exitCode, $output] = $runHook($dir);

        expect($exitCode)->toBe(0)
            ->and($output)->not->toContain('sub');
    } finally {
        $cleanup($dir);
        $cleanup($dir.'-sub');
    }
});

it('reports both gates when a merge commit also breaks CI parity', function () use ($newRepo, $midMerge, $runHook, $git, $cleanup): void {
    $dir = $newRepo();

    try {
        $midMerge($dir);

        // Stage exactly one of the mirrored CI files, leaving the resolution
        // unstaged: a hook that exits on the first gate never reaches the second.
        mkdir($dir.'/.github/workflows', 0777, true);
        file_put_contents($dir.'/.github/workflows/ci.yml', "jobs: {}\n");
        $git($dir, 'git', 'add', '.github/workflows/ci.yml');

        [$exitCode, $output] = $runHook($dir);

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('CI parity gate')
            ->and($output)->toContain('Merge resolution gate');
    } finally {
        $cleanup($dir);
    }
});

it('refuses a commit staging a composer.json that composer validate rejects', function () use ($newRepo, $runHook, $git, $cleanup): void {
    $dir = $newRepo();

    try {
        // The code-quality skill's generic gate (UAMS-Web/uams-statamic-archive#3085): invalid JSON never reaches a pull request.
        file_put_contents($dir.'/composer.json', "{ \"name\": \"enterprise/bridge\",\n");
        $git($dir, 'git', 'add', 'composer.json');

        [$exitCode, $output] = $runHook($dir);

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('composer validate failed');
    } finally {
        $cleanup($dir);
    }
});

it('passes a commit staging a composer.json that composer validate accepts', function () use ($newRepo, $runHook, $git, $cleanup): void {
    $dir = $newRepo();

    try {
        // The control for the case above: the same gate, run, lets a valid file through.
        file_put_contents($dir.'/composer.json', "{\n    \"name\": \"enterprise/bridge\",\n    \"require\": {}\n}\n");
        $git($dir, 'git', 'add', 'composer.json');

        [$exitCode, $output] = $runHook($dir);

        expect($exitCode)->toBe(0)
            ->and($output)->toContain('is valid')
            ->and($output)->not->toContain('composer validate failed');
    } finally {
        $cleanup($dir);
    }
});
