<?php

declare(strict_types=1);

use Illuminate\Support\Env;
use Tests\TestCase;

uses(TestCase::class);

/**
 * The per-worker file cache path in `config/cache.php` (UAMS-Web/uams-statamic-archive#1136, UAMS-Web/uams-statamic-archive#1985).
 *
 * Under paratest each worker gets its own cache directory, because Statamic's Stache
 * index lives in this store and a shared directory lets one worker's reset delete
 * another's index mid-test. The directory used to be keyed on the bare `TEST_TOKEN`,
 * which is the worker INDEX, 1..N, so two concurrent bands in one checkout shared
 * `data/1`, `data/2` and so on. Measured on Windows 11 before this change: five pairs
 * of concurrent `--parallel --exclude-group=serial` bands over `tests/Feature/Scopes`
 * failed in nine of ten bands with `UnexpectedValueException` from `FilesystemIterator`
 * on `cache/data/<n>\stache\...`. The path now ends in the worker's process id as well.
 *
 * Serial runs and production have no `TEST_TOKEN`, and keep the shared default.
 *
 * @command  php artisan test --compact tests/Feature/Config/WorkerCachePathTest.php
 */

/**
 * Evaluate config/cache.php under a controlled TEST_TOKEN and return the file store path.
 *
 * The ambient token is absent in a serial run and present under --parallel, so it
 * cannot exercise both branches. This re-reads the config file with the token forced.
 *
 * @param  string|null  $token  The token to expose, or null to unset it.
 * @return string The resolved path, with separators normalized.
 */
function workerCachePathWithToken(?string $token): string
{
    $restore = getenv('TEST_TOKEN');
    $restoreEnv = $_ENV['TEST_TOKEN'] ?? null;

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
        /** @var array{stores: array{file: array{path: string}}} $config */
        $config = require config_path('cache.php');
    } finally {
        $apply(is_string($restoreEnv) ? $restoreEnv : (is_string($restore) && $restore !== '' ? $restore : null));
    }

    return str_replace('\\', '/', $config['stores']['file']['path']);
}

it('keys a worker cache on its token and its process id', function (): void {
    // The pid is what keeps the same-numbered worker of a second concurrent band off
    // this path. Reverting to the bare token turns this red.
    expect(workerCachePathWithToken('7'))->toEndWith('/framework/cache/data/7-'.getmypid());
});

it('keeps the shared cache directory when no worker token is set', function (): void {
    // Production and serial runs are a single process, and `test:seed-stache` warms
    // this shared directory.
    expect(workerCachePathWithToken(null))->toEndWith('/framework/cache/data');
});

it('wires that path through to the running application', function (): void {
    /** @var string|null $token */
    $token = Env::get('TEST_TOKEN');

    /** @var string $path */
    $path = config('cache.stores.file.path');

    $expected = is_string($token) && $token !== ''
        ? '/framework/cache/data/'.$token.'-'.getmypid()
        : '/framework/cache/data';

    expect(str_replace('\\', '/', $path))->toEndWith($expected);
});
