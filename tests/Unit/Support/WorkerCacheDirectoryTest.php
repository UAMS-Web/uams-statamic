<?php

declare(strict_types=1);

use Tests\Support\WorkerCacheDirectory;

/**
 * The guard on a paratest worker's exit cleanup (UAMS-Web/uams-statamic-archive#1985). A worker deletes its cache
 * directories when it exits, and this predicate is all that stops it deleting the
 * shared default a serial run uses — the seeded Stache, the scope-alias cache, and
 * the committed `.gitignore`.
 *
 * @command  php artisan test --compact tests/Unit/Support/WorkerCacheDirectoryTest.php
 */
it('owns a directory ending in its own pid', function (string $directory): void {
    expect(WorkerCacheDirectory::isOwnedBy($directory, 4812))->toBeTrue();
})->with([
    'forward slashes' => ['C:/repo/storage/framework/cache/data/3-4812'],
    'backslashes' => ['C:\\repo\\storage\\framework/cache/data/3-4812'],
    'trailing separator' => ['/repo/storage/app/htmlpurifier/3-4812/'],
]);

it('never owns the shared directory a serial run uses', function (string $directory): void {
    expect(WorkerCacheDirectory::isOwnedBy($directory, 4812))->toBeFalse();
})->with([
    'shared file cache' => ['/repo/storage/framework/cache/data'],
    'shared purifier cache' => ['/repo/storage/app/htmlpurifier'],
]);

it('does not own another process\'s directory, including a pid it merely ends with', function (string $directory): void {
    expect(WorkerCacheDirectory::isOwnedBy($directory, 812))->toBeFalse();
})->with([
    'a different pid' => ['/repo/storage/framework/cache/data/3-9999'],
    'a longer pid ending in the same digits' => ['/repo/storage/framework/cache/data/3-4812'],
]);

it('owns nothing when the pid is unavailable', function (): void {
    expect(WorkerCacheDirectory::isOwnedBy('/repo/storage/framework/cache/data/3-4812', false))->toBeFalse();
});
