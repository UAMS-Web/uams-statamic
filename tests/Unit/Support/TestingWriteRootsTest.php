<?php

declare(strict_types=1);

use Tests\Support\TestingWriteRoots;

/**
 * The boot guard that keeps a test application's write roots out of the live tree (#1160).
 * A `--mutate` run on `ContentRoot` handed the harness the live cache directory and
 * content root, and the seed deleted the tracked cache `.gitignore` and left
 * `content.band.lock` at the repository root.
 *
 * @command  php artisan test --compact tests/Unit/Support/TestingWriteRootsTest.php
 */
it('finds nothing outside when every root is under the testing directory', function (string $root): void {
    expect(TestingWriteRoots::outside('/repo/storage/framework/testing', ['root' => $root]))->toBe([]);
})->with([
    'serial file cache' => ['/repo/storage/framework/testing/cache/data'],
    'worker file cache' => ['/repo/storage/framework/testing/cache/data/3-4812'],
    'content mirror' => ['/repo/storage/framework/testing/content-mirror'],
    'trailing separator' => ['/repo/storage/framework/testing/content-mirror/'],
]);

it('compares Windows paths written with either separator', function (): void {
    expect(TestingWriteRoots::outside('C:\\repo\\storage\\framework\\testing\\', [
        'file cache' => 'C:/repo/storage/framework/testing/cache/data',
        'content root' => 'C:\\repo\\storage\\framework\\testing\\content-mirror',
        'live cache' => 'C:\\repo\\storage\\framework\\cache\\data',
    ]))->toBe(['live cache']);
});

it('names only the roots outside, including a sibling that shares the prefix', function (): void {
    expect(TestingWriteRoots::outside('/repo/storage/framework/testing', [
        'file cache' => '/repo/storage/framework/testing/cache/data',
        'content root' => '/repo/content',
        'old cache' => '/repo/storage/framework/testing-old/cache/data',
    ]))->toBe(['content root', 'old cache']);
});

it('refuses the live roots a mutated ContentRoot resolves to', function (string $name, string $root): void {
    expect(fn () => TestingWriteRoots::assertInside('/repo/storage/framework/testing', [
        'mirror' => '/repo/storage/framework/testing/content-mirror',
        $name => $root,
    ]))->toThrow(RuntimeException::class, \sprintf('The test %s resolves to %s, outside /repo/storage/framework/testing', $name, $root));
})->with([
    'live file cache' => ['file cache', '/repo/storage/framework/cache/data'],
    'content submodule' => ['content root', '/repo/content'],
]);
