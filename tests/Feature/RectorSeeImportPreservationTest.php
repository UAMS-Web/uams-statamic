<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;
use Tests\Fixtures\Rector\SeeOnlyImport;

/**
 * Regression guard for UAMS-Web/uams-statamic-archive#1131. Rector's auto-import step removes unused `use`
 * imports, but its remover is not `{@see}`-aware: with removal enabled it strips
 * imports referenced only by a `{@see ClassName}` docblock tag, breaking the
 * short-name resolution of those references. PHPStan does not treat `{@see}` as
 * a type position, so the static-analysis gate stays green — the false-green
 * this guard closes.
 *
 * `rector.php` disables the removal (`withImportNames(removeUnusedImports:
 * false)`) and lets Pint's docblock-aware `no_unused_imports` own unused-import
 * removal instead. This test runs the real project Rector config over a fixture
 * whose only import is `{@see}`-only ({@see SeeOnlyImport}) and asserts Rector
 * leaves it untouched. Re-enabling removal makes Rector strip the import — Rector
 * then reports a pending change and this guard turns red.
 *
 * A pure subprocess check; it deliberately avoids booting the framework (the
 * project only binds Pest's TestCase to tests/Browser).
 */
$root = dirname(__DIR__, 2);
$fixture = $root.'/tests/Fixtures/Rector/SeeOnlyImport.php';
$removedImportLine = '-use Tests\Fixtures\Rector\Referenced\DocblockReferencedClass;';

it('leaves an import referenced only by a {@see} docblock tag untouched', function () use ($root, $fixture, $removedImportLine) {
    // Point the Rector subprocess at a private temp dir so its cache
    // (`sys_get_temp_dir()/rector_cached_files`, otherwise machine-global) is
    // isolated per run. Without this, a concurrent Rector process — parallel
    // afk-wave worktrees, `scripts/local-ci.mjs`, or a `composer refactor` while
    // the suite runs — races the shared cache directory and fails this guard
    // spuriously. A fresh dir starts empty, so no `--clear-cache` (and its
    // delete race) is needed; a config regression still changes the cache key
    // and turns the guard red.
    $tmp = sys_get_temp_dir().'/rector-guard-'.bin2hex(random_bytes(6));
    mkdir($tmp);

    try {
        $process = new Process(
            [PHP_BINARY, 'vendor/bin/rector', 'process', $fixture, '--dry-run', '--no-ansi'],
            $root,
            ['TMPDIR' => $tmp, 'TMP' => $tmp, 'TEMP' => $tmp],
        );
        $process->run();

        // Rector exits 0 with no pending changes when the import survives; a
        // stripped import instead exits 2 and prints the removed `use` line.
        expect($process->getExitCode())->toBe(0);
        expect($process->getOutput())->toContain('Rector is done!');
        expect($process->getOutput())->not->toContain($removedImportLine);
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
});
