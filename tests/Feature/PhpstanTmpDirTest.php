<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * Guard for UAMS-Web/uams-statamic-archive#1879. PHPStan's default `tmpDir` is `sys_get_temp_dir()/phpstan` —
 * machine-global, so the primary checkout, the local-CI runner's worktree, and
 * every `.claude/worktrees/*` share one cache directory.
 *
 * That cache stores ABSOLUTE phar paths. Delete the worktree that populated it and
 * every stored path dangles, so the next run in a LIVE checkout dies with ~30
 * internal errors naming a `phar://…/.claude/worktrees/<gone>/vendor/phpstan/…`
 * path that no longer exists — while blaming whichever source file happened to be
 * under analysis. Hit while validating UAMS-Web/uams-statamic-archive#1848: a worktree deleted days earlier, an
 * error attributed to a test that PR never touched, and a red `larastan` job
 * indistinguishable from a real defect in the diff.
 *
 * `phpstan.neon.dist` therefore sets a RELATIVE `tmpDir`, which PHPStan resolves
 * against that file's own directory — giving every checkout its own cache. This
 * asserts the property that actually matters (the RESOLVED path lands inside this
 * checkout), not the config text, because `tmpDir: /tmp/phpstan` would satisfy a
 * text check while reinstating exactly the sharing the key exists to prevent.
 *
 * `dump-parameters` is the instrument because there is no `--tmp-dir` flag and no
 * other way to observe the resolved value: the container computes it, so only
 * PHPStan can report it.
 *
 * A pure subprocess check that never boots the framework, matching the sibling
 * {@see RectorProfileSplitTest} and {@see RectorSeeImportPreservationTest} guards.
 */
$root = dirname(__DIR__, 2);

/**
 * Normalize a filesystem path for comparison across platforms.
 *
 * Windows hands back backslashes from `__DIR__` and from PHPStan alike, and CI
 * hands back forward slashes; comparing raw strings would pass on one and fail on
 * the other. Trailing separators are dropped so a prefix test cannot miss on a
 * lone slash.
 */
$normalize = fn (string $path): string => rtrim(str_replace('\\', '/', $path), '/');

/**
 * Read the resolved `tmpDir` out of `phpstan dump-parameters`.
 *
 * @param  string  $root  Absolute path to the checkout root.
 * @return array{0: int|null, 1: string, 2: string|null} Exit code, combined output, and the tmpDir line's value.
 */
$dumpTmpDir = function (string $root): array {
    $process = new Process(
        [PHP_BINARY, 'vendor/bin/phpstan', 'dump-parameters', '--configuration=phpstan.neon.dist', '--no-ansi'],
        $root,
    );
    $process->setTimeout(300);
    $process->run();

    $output = $process->getOutput().$process->getErrorOutput();

    // `tmpDir` is dumped at the top level, one key per line. Anchored to the line
    // start so a nested occurrence inside another parameter cannot be read as the
    // resolved value.
    preg_match('/^tmpDir:\s*(.+)$/m', $output, $matches);

    return [$process->getExitCode(), $output, isset($matches[1]) ? trim($matches[1]) : null];
};

it('resolves phpstan\'s tmpDir inside this checkout rather than the shared system temp dir', function () use ($root, $normalize, $dumpTmpDir): void {
    [$exitCode, $output, $tmpDir] = $dumpTmpDir($root);

    expect($exitCode)->toBe(0, "phpstan dump-parameters failed:\n{$output}");
    expect($tmpDir)->not->toBeNull('phpstan dump-parameters printed no tmpDir line.');

    // The load-bearing assertion. A path inside the checkout is per-worktree by
    // construction, so no two checkouts can share a cache and deleting one takes
    // only its own with it.
    expect($normalize((string) $tmpDir))->toStartWith($normalize($root).'/');

    // The crisp negative control: this is the exact default the key overrides, and
    // the one value that would silently restore the machine-global sharing.
    expect($normalize((string) $tmpDir))->not->toBe($normalize(sys_get_temp_dir().'/phpstan'));
});

it('git-ignores the cache directory so it cannot be committed or read as worktree drift', function () use ($root, $normalize, $dumpTmpDir): void {
    [, , $tmpDir] = $dumpTmpDir($root);
    expect($tmpDir)->not->toBeNull();

    $relative = ltrim(substr($normalize((string) $tmpDir), strlen($normalize($root))), '/');

    // Two failures ride on this beyond the obvious "don't commit a 1.3 GB cache".
    // `scripts/local-ci.mjs` refuses a reused worktree whose `git status` is not
    // clean (UAMS-Web/uams-statamic-archive#1889), so an un-ignored cache directory would abort every local-CI
    // run the moment `composer stan` had populated it.
    $process = new Process(['git', 'check-ignore', '--quiet', $relative.'/probe'], $root);
    $process->run();

    expect($process->getExitCode())->toBe(0, "git does not ignore {$relative}/ — add it to .gitignore.");
});
