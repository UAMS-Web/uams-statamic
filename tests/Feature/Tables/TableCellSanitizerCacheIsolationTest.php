<?php

declare(strict_types=1);

use App\Services\Tables\TableCellSanitizer;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Statamic\Facades\Antlers;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Regression guard for UAMS-Web/uams-statamic-archive#1853: the table-cell sanitizer's HTMLPurifier definition
 * cache must neither race on creation nor be shared across paratest workers.
 *
 * Two table tests failed together in a `--parallel --exclude-group=serial` band
 * with `ErrorException: mkdir(): File exists` and passed when re-run alone. Both
 * were the first test in their file to reach {@see TableCellSanitizer}, which
 * created `storage/app/htmlpurifier` through `File::ensureDirectoryExists()` —
 * a check-then-create whose second half is Laravel's UNGUARDED `mkdir()`. Two
 * workers booting into a cold cache together therefore both saw the directory
 * absent, and the loser's warning was promoted to an ErrorException by the test
 * environment's error handler.
 *
 * Reproduced outside the suite at 12 failures in 50 children (10 concurrent
 * processes released from a shared barrier against a cold cache, 5 rounds), and
 * 0 in 100 after the fix — on the shared path, with no worker token in play, so
 * the race-safety below is verified independently of the per-worker isolation.
 *
 * @command  php artisan test --compact tests/Feature/Tables/TableCellSanitizerCacheIsolationTest.php
 */

/** @var list<string> $tableCacheThrowawayDirs */
$tableCacheThrowawayDirs = [];

afterEach(function () use (&$tableCacheThrowawayDirs): void {
    // Deliberately a real Filesystem, not the File facade. The race test below
    // swaps a partial mock onto that facade, and `deleteDirectory()` opens with
    // its own `isDirectory()` check — routed through the mock, the cleanup
    // silently no-ops and leaks a directory into storage/ on every run.
    $files = new Filesystem;

    foreach ($tableCacheThrowawayDirs as $dir) {
        $files->deleteDirectory($dir);
    }

    $tableCacheThrowawayDirs = [];
});

/**
 * Warm the definition cache at $dir, then truncate it the way a concurrent
 * writer would — HTMLPurifier writes each `.ser` with a non-atomic
 * `file_put_contents()`, so a reader can catch a partial file and `unserialize()`
 * warns, which Laravel promotes to an ErrorException.
 *
 * @param  string  $dir  The serializer-cache directory to corrupt
 */
$corruptDefinitionCache = function (string $dir): void {
    config()->set('tables.cell_sanitizer.cache_path', $dir);

    (new TableCellSanitizer)->sanitize('warm the cache');

    $files = new Filesystem;

    foreach ($files->allFiles($dir.DIRECTORY_SEPARATOR.'HTML') as $serialized) {
        $files->put($serialized->getPathname(), substr($files->get($serialized->getPathname()), 0, 40));
    }
};

// ---
// Per-worker isolation
// ---

/**
 * Evaluate config/tables.php under a controlled TEST_TOKEN.
 *
 * The ambient token cannot exercise both branches — it is absent in a serial run
 * and present under --parallel — so asserting on it alone would leave the gate
 * (which runs serially) unable to tell a tokenized path from a shared one. This
 * re-reads the config file with the token forced either way instead.
 *
 * @param  string|null  $token  The token to expose, or null to unset it
 * @return string The resolved cache path, with separators normalized
 */
$resolveCachePathWithToken = function (?string $token): string {
    $restore = [$_ENV['TEST_TOKEN'] ?? null, $_SERVER['TEST_TOKEN'] ?? null, getenv('TEST_TOKEN')];

    $apply = static function (?string $value): void {
        if ($value === null) {
            unset($_ENV['TEST_TOKEN'], $_SERVER['TEST_TOKEN']);
            putenv('TEST_TOKEN');

            return;
        }

        $_ENV['TEST_TOKEN'] = $value;
        $_SERVER['TEST_TOKEN'] = $value;
        putenv('TEST_TOKEN='.$value);
    };

    $apply($token);

    try {
        /** @var array{cell_sanitizer: array{cache_path: string}} $config */
        $config = require config_path('tables.php');
    } finally {
        $apply(\is_string($restore[0]) ? $restore[0] : (\is_string($restore[2]) && $restore[2] !== '' ? $restore[2] : null));
    }

    return str_replace('\\', '/', $config['cell_sanitizer']['cache_path']);
};

it('scopes the purifier definition cache to the per-worker token', function () use ($resolveCachePathWithToken): void {
    // Each paratest worker must get its own directory, so ~12 workers neither
    // race to create one tree nor read each other's half-written `.ser` files.
    // Reverting the tokenization in config/tables.php turns this red. The pid suffix
    // keeps the same-numbered worker of a second concurrent band off this path (UAMS-Web/uams-statamic-archive#1985).
    expect($resolveCachePathWithToken('probe7'))->toEndWith('/app/htmlpurifier/probe7-'.getmypid());
});

it('keeps one shared purifier cache when no worker token is set', function () use ($resolveCachePathWithToken): void {
    // Serial runs and production are a single process with no race, and they
    // want the warm cache — tokenizing there would recompile the definition on
    // every deploy for no benefit.
    expect($resolveCachePathWithToken(null))->toEndWith('/app/htmlpurifier');
});

it('wires that tokenized path through to the running application', function (): void {
    // The two cases above read the file directly; this proves the value the
    // service actually resolves comes from it.
    /** @var string|null $token */
    $token = Env::get('TEST_TOKEN');

    /** @var string $path */
    $path = config('tables.cell_sanitizer.cache_path');

    $expected = \is_string($token) && $token !== ''
        ? '/app/htmlpurifier/'.$token.'-'.getmypid()
        : '/app/htmlpurifier';

    expect(str_replace('\\', '/', $path))->toEndWith($expected);
});

// ---
// Creation race-safety
// ---

it('survives a sibling worker creating the cache directory between the check and the create', function () use (&$tableCacheThrowawayDirs): void {
    $dir = storage_path('framework/testing/table-cell-cache/'.uniqid('race', true));
    $tableCacheThrowawayDirs[] = $dir;

    // The sibling worker has already won the race — the directory is on disk.
    File::makeDirectory($dir, 0755, true, true);

    config()->set('tables.cell_sanitizer.cache_path', $dir);

    // Reproduce the losing worker's stale view deterministically: its
    // `isDirectory()` check answers false even though the directory now exists,
    // which is exactly the window between the check and the create. Pinned to a
    // single call because the post-create re-check must read the filesystem
    // directly — routing it back through this same check is what let the
    // analyser (correctly) call it a foregone conclusion.
    File::partialMock()
        ->shouldReceive('isDirectory')
        ->with($dir)
        ->once()
        ->andReturnFalse();

    // Under the old `File::ensureDirectoryExists()` this raises
    // `mkdir(): File exists`, promoted to an ErrorException by the test error
    // handler. The `$force` argument suppresses it and the re-check resolves it.
    expect((new TableCellSanitizer)->sanitize('<a href="https://uams.edu">UAMS</a>'))
        ->toBe('<a href="https://uams.edu">UAMS</a>');
});

it('serializes the compiled definition into the configured directory', function () use (&$tableCacheThrowawayDirs): void {
    // Proves the resolved path is the one HTMLPurifier actually writes to, so
    // the per-worker scoping above reaches the files and not just the config.
    $dir = storage_path('framework/testing/table-cell-cache/'.uniqid('serialize', true));
    $tableCacheThrowawayDirs[] = $dir;

    config()->set('tables.cell_sanitizer.cache_path', $dir);

    (new TableCellSanitizer)->sanitize('<strong>warm the purifier</strong>');

    expect(File::isDirectory($dir))->toBeTrue()
        ->and(File::isDirectory($dir.DIRECTORY_SEPARATOR.'HTML'))->toBeTrue()
        ->and(File::allFiles($dir.DIRECTORY_SEPARATOR.'HTML'))->not->toBeEmpty();
});

// ---
// Failing closed (the sanitizer is an XSS control)
// ---

it('does not throw when the definition cache is corrupt, and still applies the allowlist', function () use (&$tableCacheThrowawayDirs, $corruptDefinitionCache): void {
    $dir = storage_path('framework/testing/table-cell-cache/'.uniqid('corrupt', true));
    $tableCacheThrowawayDirs[] = $dir;

    $corruptDefinitionCache($dir);

    $log = Log::spy();

    // A fresh instance so the corrupt cache is actually read, then recovered
    // from by recompiling in-process — the link survives, the script does not.
    expect((new TableCellSanitizer)->sanitize('<a href="https://uams.edu">UAMS</a><script>alert(1)</script>'))
        ->toBe('<a href="https://uams.edu">UAMS</a>');

    $log->shouldHaveReceived('warning')->once();
});

it('never leaks the raw cell through the Antlers modifier when the sanitizer fails', function () use (&$tableCacheThrowawayDirs, $corruptDefinitionCache): void {
    // The regression this pins: Statamic catches a throwing modifier and returns
    // the PRE-modifier value, and resources/views/sets/_table.antlers.html emits
    // it unescaped. With `app.debug` off — production — nothing is rethrown, so
    // a throw inside sanitize() publishes the raw cell verbatim. Reproduced
    // during review as `<script>alert(document.cookie)</script>` rendered live.
    config()->set('app.debug', false);

    $dir = storage_path('framework/testing/table-cell-cache/'.uniqid('leak', true));
    $tableCacheThrowawayDirs[] = $dir;

    $corruptDefinitionCache($dir);

    // The service is a singleton (AppServiceProvider), so the warm instance the
    // helper just used would otherwise mask the corruption from the modifier.
    app()->forgetInstance(TableCellSanitizer::class);

    $out = (string) Antlers::parse(
        '{{ value | sanitize_table_cell }}',
        ['value' => '<script>alert(document.cookie)</script>ok']
    );

    expect($out)->not->toContain('<script>')
        ->and($out)->not->toContain('document.cookie')
        ->and($out)->toContain('ok');
});

it('still renders a sanitized cell when the logger itself throws during recovery', function () use (&$tableCacheThrowawayDirs, $corruptDefinitionCache): void {
    // UAMS-Web/uams-statamic-archive#1869. sanitizeAfterFailure() logs BEFORE it returns anything safe, and
    // Monolog throws UnexpectedValueException when it cannot open its stream, so
    // that throw escaped sanitize() and took the whole render down with it.
    //
    // This is one fault, not two: a full disk (or a storage/logs that cannot be written)
    // makes HTMLPurifier's own unsuppressed file_put_contents() of its definition
    // throw AND makes the log write throw, so the condition that triggers the
    // recovery is the same one that breaks reporting it.
    //
    // Measured end to end, the escape did NOT leak the raw cell the way UAMS-Web/uams-statamic-archive#1853's
    // fail-open did — Statamic's own handler is broken by the same fault, so the
    // render 500s. What was lost was a page that could have rendered correctly.
    config()->set('app.debug', false);

    $dir = storage_path('framework/testing/table-cell-cache/'.uniqid('logger', true));
    $tableCacheThrowawayDirs[] = $dir;

    $corruptDefinitionCache($dir);

    // A real channel that cannot be opened, rather than a strict mock: Mockery would throw on
    // the FIRST unexpected facade call from anywhere in the render, so a revert
    // reds this test for the wrong reason and proves nothing.
    $blocker = $dir.DIRECTORY_SEPARATOR.'log-blocker';
    File::put($blocker, 'not a directory');

    config()->set('logging.default', 'broken');
    config()->set('logging.channels.broken', [
        'driver' => 'single',
        'path' => $blocker.DIRECTORY_SEPARATOR.'cannot'.DIRECTORY_SEPARATOR.'exist.log',
    ]);
    app()->forgetInstance('log');

    // The service is a singleton, so the warm instance the helper just used would
    // otherwise mask the corruption from the modifier.
    app()->forgetInstance(TableCellSanitizer::class);

    $out = (string) Antlers::parse(
        '{{ value | sanitize_table_cell }}',
        ['value' => '<script>alert(document.cookie)</script>ok']
    );

    expect($out)->toContain('ok')
        ->and($out)->not->toContain('<script>')
        ->and($out)->not->toContain('document.cookie');
});

// ---
// Storage that cannot be written
// ---

it('still sanitizes when the cache directory cannot be created', function () use (&$tableCacheThrowawayDirs): void {
    $base = storage_path('framework/testing/table-cell-cache/'.uniqid('blocked', true));
    $tableCacheThrowawayDirs[] = $base;

    File::makeDirectory($base, 0755, true, true);

    // A *file* where the cache directory's parent needs to be, so every mkdir
    // beneath it fails for a reason no retry can fix.
    $blocker = $base.DIRECTORY_SEPARATOR.'blocker';
    File::put($blocker, 'not a directory');

    config()->set('tables.cell_sanitizer.cache_path', $blocker.DIRECTORY_SEPARATOR.'cache');

    $log = Log::spy();

    // The definition is compiled in-process instead of being serialized, so the
    // sanitizer degrades rather than warning on every cell (or, as the unguarded
    // mkdir did, throwing).
    expect((new TableCellSanitizer)->sanitize('<a href="https://uams.edu">UAMS</a><script>alert(1)</script>'))
        ->toBe('<a href="https://uams.edu">UAMS</a>');

    $log->shouldHaveReceived('warning')->once();
});
