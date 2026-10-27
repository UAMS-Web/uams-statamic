<?php

declare(strict_types=1);

use App\Support\Cache\RetryingFileStore;
use Illuminate\Filesystem\Filesystem;
use Tests\TestCase;

uses(TestCase::class);

covers(RetryingFileStore::class);

/**
 * Unit coverage for App\Support\Cache\RetryingFileStore (UAMS-Web/uams-statamic-archive#1381).
 *
 * Mutation: 91.67% (11/12). The survivor is RemoveFunctionCall on ``usleep``:
 * timing-only and cannot be annotated (ignore attaches to children of the call node,
 * not the call; see RedirectRuleMatcher). Every killable mutant is dead.
 *
 * @command  php artisan test --compact tests/Unit/Support/RetryingFileStoreTest.php
 */

/**
 * Pad Pest's mutant-child timeout once per process under ``--mutate``.
 */
beforeEach(function (): void {
    static $padded = false;
    if ($padded) {
        return;
    }

    if (runningUnderMutate()) {
        usleep(20_000_000);
        $padded = true;
    }
});

/**
 * Build a {@see RetryingFileStore} over a fake filesystem whose `put()` delegates
 * to `$onPut`, so a test can make the write throw a transient Permission-denied
 * and then succeed. `exists()` is stubbed true so the parent
 * `ensureCacheDirectoryExists()` never touches the real disk, keeping this a pure
 * unit test.
 */
function retryingFileStoreWith(Closure $onPut): RetryingFileStore
{
    $files = new class($onPut) extends Filesystem
    {
        public function __construct(private Closure $onPut) {}

        public function exists($path)
        {
            return true;
        }

        public function put($path, $contents, $lock = false)
        {
            /** @var bool|int $result */
            $result = ($this->onPut)($path, $contents);

            return $result;
        }
    };

    return new RetryingFileStore($files, sys_get_temp_dir().DIRECTORY_SEPARATOR.'rfs-'.uniqid(), null);
}

it('retries a transient Permission-denied write and then succeeds', function (): void {
    $attempts = 0;

    $store = retryingFileStoreWith(function (string $path, string $contents) use (&$attempts) {
        $attempts++;

        if ($attempts < 3) {
            throw new ErrorException(sprintf('file_put_contents(%s): Failed to open stream: Permission denied', $path));
        }

        return strlen($contents);
    });

    expect($store->put('stache/keys/form-submissions', ['a', 'b'], 60))->toBeTrue();
    expect($attempts)->toBe(3);
});

it('gives up after the maximum attempts and rethrows the Permission-denied error', function (): void {
    // GreaterToGreaterOrEqual on ``$attempt >= MAX`` would allow a sixth try.
    $attempts = 0;

    $store = retryingFileStoreWith(function (string $path) use (&$attempts) {
        $attempts++;

        throw new ErrorException(sprintf('file_put_contents(%s): Failed to open stream: Permission denied', $path));
    });

    expect(fn () => $store->put('stache/keys/form-submissions', 'v', 60))
        ->toThrow(ErrorException::class, 'Permission denied');

    // MAX_ATTEMPTS — the write is tried five times before giving up.
    expect($attempts)->toBe(5);
});

it('does not retry an unrelated write error', function (): void {
    // BooleanOrToBooleanAnd would keep retrying a non-Permission-denied error.
    $attempts = 0;

    $store = retryingFileStoreWith(function () use (&$attempts) {
        $attempts++;

        throw new ErrorException('disk full');
    });

    expect(fn () => $store->put('stache/keys/form-submissions', 'v', 60))
        ->toThrow(ErrorException::class, 'disk full');

    // A non-Permission-denied error propagates on the first attempt.
    expect($attempts)->toBe(1);
});

it('succeeds on the first attempt without retrying', function (): void {
    $attempts = 0;

    $store = retryingFileStoreWith(function (string $path, string $contents) use (&$attempts) {
        $attempts++;

        return strlen($contents);
    });

    expect($store->put('stache/keys/form-submissions', 'ok', 60))->toBeTrue();
    expect($attempts)->toBe(1);
});
