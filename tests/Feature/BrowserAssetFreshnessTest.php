<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Tests\Support\BrowserAssetFreshness;
use Tests\TestCase;

/**
 * The stale-asset guard for browser runs (UAMS-Web/uams-statamic-archive#2252).
 *
 * Driven against a fixture pair rather than the real tree, so the failing arms can
 * be watched without backdating this checkout's own assets — which would make the
 * test destructive and order-dependent.
 *
 * Every arm is two-sided on purpose. A guard is only worth having if it has been
 * seen to fail, and this one exists because a *passing* browser suite was the
 * misleading state: stale JS produced plausible behavioral failures on two
 * machines in one evening, and nothing anywhere said the assets were old.
 *
 * @command  php artisan test --compact tests/Feature/BrowserAssetFreshnessTest.php
 */
uses(TestCase::class);

$root = '';

beforeEach(function () use (&$root): void {
    $root = storage_path('framework/testing/asset-freshness-'.uniqid('', true));
    File::ensureDirectoryExists($root.'/src');
    File::put($root.'/src/app.js', "// source\n");
});

afterEach(function () use (&$root): void {
    if ($root !== '' && File::isDirectory($root)) {
        File::deleteDirectory($root);
    }
});

it('passes when the manifest is newer than every source', function () use (&$root): void {
    /** @var string $root */
    File::put($root.'/manifest.json', '{}');
    touch($root.'/src/app.js', time() - 120);
    touch($root.'/manifest.json', time() - 10);

    // Asserting the absence of a throw directly, rather than calling the guard
    // and then asserting something trivially true — which would pass even if this
    // arm stopped exercising the guard at all.
    expect(throwableFrom(fn () => BrowserAssetFreshness::assertFresh($root.'/src', $root.'/manifest.json')))->toBeNull();
});

it('fails when a source is newer than the manifest, naming both times and the command', function () use (&$root): void {
    /** @var string $root */
    File::put($root.'/manifest.json', '{}');
    touch($root.'/manifest.json', time() - 120);
    touch($root.'/src/app.js', time() - 10);

    expect(fn () => BrowserAssetFreshness::assertFresh($root.'/src', $root.'/manifest.json'))
        ->toThrow(RuntimeException::class);

    try {
        BrowserAssetFreshness::assertFresh($root.'/src', $root.'/manifest.json');
    } catch (RuntimeException $e) {
        // The message is the whole remedy: a bare "assets are stale" would leave
        // the reader to work out which side is old and what to run.
        expect($e->getMessage())
            ->toContain('npm run build')
            ->toContain('newest source')
            ->toContain('built manifest');
    }
});

it('fails the same way when the manifest is missing entirely', function () use (&$root): void {
    /** @var string $root */
    // The positive control by construction: a guard that can only compare two
    // present files would PASS on a checkout that never built, which is the
    // strictly worse version of the state it exists to catch.
    expect(fn () => BrowserAssetFreshness::assertFresh($root.'/src', $root.'/nope.json'))
        ->toThrow(RuntimeException::class);
});

it('does not build anything', function () use (&$root): void {
    /** @var string $root */
    File::put($root.'/manifest.json', '{}');
    touch($root.'/manifest.json', time() - 120);
    touch($root.'/src/app.js', time() - 10);

    try {
        BrowserAssetFreshness::assertFresh($root.'/src', $root.'/manifest.json');
    } catch (RuntimeException) {
        // expected
    }

    // A suite that silently repairs its own inputs masks the drift it exists to
    // surface, so the manifest must still be stale after the guard has run.
    expect((int) filemtime($root.'/manifest.json'))
        ->toBeLessThan((int) filemtime($root.'/src/app.js'));
});
