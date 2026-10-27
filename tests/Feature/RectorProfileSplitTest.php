<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

/**
 * Guard for UAMS-Web/uams-statamic-archive#1720. `rector.php` carries two profiles over one rule corpus: the
 * **gating** profile the `rector` CI job enforces, and the **sweep** profile
 * (`rector-sweep.php`) that additionally enables five rules whose suggestions are
 * judgment calls — `ReadOnlyClassRector`, `ReadOnlyPropertyRector`,
 * `LocallyCalledStaticMethodToNonStaticRector`, `RemoveUselessVarTagRector`, and
 * `RemoveDuplicatedReturnSelfDocblockRector`.
 *
 * The split has to hold in both directions, so this asserts both:
 *
 * - A rule leaking *into* the gate makes a semantic rewrite mandatory on pain of
 *   a red build — `LocallyCalledStaticMethodToNonStaticRector` in particular is
 *   unsound at the config's `app/`-only path scope and would demote a method
 *   still called statically from `tests/`, an Antlers template, or
 *   `vendor/uams-web/*`.
 * - A rule silently dropping *out of the sweep* — the likelier regression, since
 *   the split is implemented by skipping — would quietly shrink `composer
 *   refactor:sweep` to the gate with no visible symptom.
 *
 * `tests/Fixtures/Rector/SweepOnly/` holds one minimal file per excluded rule,
 * shaped so that **no gating rule fires on any of them**. That is what lets the
 * gate assertion be the crisp "Rector is done!" rather than a substring hunt
 * through a diff. The fixtures are deliberately un-idiomatic; each carries a
 * "do not fix this file" docblock.
 *
 * A pure subprocess check; it deliberately avoids booting the framework, matching
 * the sibling {@see RectorSeeImportPreservationTest} guard for UAMS-Web/uams-statamic-archive#1131.
 */
$root = dirname(__DIR__, 2);
$fixtures = $root.'/tests/Fixtures/Rector/SweepOnly';

/** The five rules the gating profile holds back, by short name. */
$sweepOnlyRules = [
    'ReadOnlyClassRector',
    'ReadOnlyPropertyRector',
    'LocallyCalledStaticMethodToNonStaticRector',
    'RemoveUselessVarTagRector',
    'RemoveDuplicatedReturnSelfDocblockRector',
];

/**
 * Run Rector's dry-run over the fixture directory under one profile.
 *
 * Points the subprocess at a private temp dir so its cache
 * (`sys_get_temp_dir()/rector_cached_files`, otherwise machine-global) is
 * isolated per run. Two things need that here. Rector's file cache does **not**
 * key on the active rule set, so a warm cache left by a *gate*-profile run makes
 * a later *sweep*-profile run silently under-report — measured over `app/` while
 * building UAMS-Web/uams-statamic-archive#1720: 124 files cold versus 92 warm. And a concurrent Rector process
 * (parallel afk-wave worktrees, `scripts/local-ci.mjs`, a `composer refactor`
 * while the suite runs) would otherwise race the shared directory. A fresh dir
 * starts empty, so no `--clear-cache` — and no delete race — is needed.
 *
 * @param  string  $fixtures  Absolute path to the fixture directory to analyze.
 * @param  string|null  $config  Config file to pass via `--config`, or null for the default gating profile.
 * @return array{0: int|null, 1: string} The exit code and combined output.
 */
$runRector = function (string $root, string $fixtures, ?string $config): array {
    $tmp = sys_get_temp_dir().'/rector-profile-'.bin2hex(random_bytes(6));
    mkdir($tmp);

    $command = [PHP_BINARY, 'vendor/bin/rector', 'process', $fixtures, '--dry-run', '--no-ansi', '--no-progress-bar'];

    if ($config !== null) {
        $command[] = '--config='.$config;
    }

    try {
        // Symfony's default 60-second limit is not enough during a parallel band
        // (UAMS-Web/uams-statamic-archive#3297). On Windows 11 with Herd PHP 8.4 the gating-profile child took
        // 11.8 to 33.8 seconds on a quiet machine and 7.3 to 32.7 seconds (80
        // runs) during a 12-process band, and one band on UAMS-Web/uams-statamic-archive#3297 ran it past 60
        // seconds. 300 seconds is five times that 60 and about nine times the
        // slowest measured run, and is the limit `PhpstanTmpDirTest` gives its
        // PHPStan child.
        $process = new Process($command, $root, ['TMPDIR' => $tmp, 'TMP' => $tmp, 'TEMP' => $tmp], timeout: 300);
        $process->run();

        return [$process->getExitCode(), $process->getOutput().$process->getErrorOutput()];
    } finally {
        // Best-effort recursive cleanup; a leftover temp dir is harmless.
        $remove = function (string $path) use (&$remove): void {
            foreach (glob($path.'/*') ?: [] as $item) {
                is_dir($item) ? $remove($item) : @unlink($item);
            }
            @rmdir($path);
        };
        $remove($tmp);
    }
};

it('holds every sweep-only rule out of the gating profile', function () use ($root, $fixtures, $runRector, $sweepOnlyRules): void {
    [$exitCode, $output] = $runRector($root, $fixtures, null);

    // Every fixture is shaped so no *gating* rule fires on it, so the gating
    // profile must find nothing at all. Rector exits 0 and prints "Rector is
    // done!" when there is no pending change; any leaked rule instead exits 2
    // and names itself in the applied-rules list.
    expect($output)->toContain('Rector is done!');
    expect($exitCode)->toBe(0);

    foreach ($sweepOnlyRules as $rule) {
        expect($output)->not->toContain($rule);
    }
});

it('keeps every sweep-only rule active under the sweep profile', function () use ($root, $fixtures, $runRector, $sweepOnlyRules): void {
    [$exitCode, $output] = $runRector($root, $fixtures, 'rector-sweep.php');

    // The mirror assertion: splitting must remove nothing from the on-demand
    // run. One fixture per rule, so all five report as pending changes.
    expect($exitCode)->toBe(2);
    expect($output)->toContain('5 files would have been changed');

    foreach ($sweepOnlyRules as $rule) {
        expect($output)->toContain($rule);
    }
});
