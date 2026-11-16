<?php

declare(strict_types=1);

use App\Tags\ViteAsset;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Vite as ViteManager;
use Illuminate\Support\Facades\Vite;
use Tests\Support\ThrowawayRoot;
use Tests\TestCase;

/**
 * Pin asset parameter rejection and delegation to the real Vite URL resolver.
 *
 * Linux, PHP 8.4.27, PCOV: 9/9 covered mutants killed with no survivors.
 * An identical-copy mutant child passes; returning an empty URL fails.
 *
 * @command  php artisan test --compact tests/Feature/Tags/ViteAssetTagTest.php
 */
uses(TestCase::class);

covers(ViteAsset::class);

beforeEach(function (): void {
    // Isolate the hot file without starting an asset server or using a build.
    $root = storage_path('framework/testing/'.ThrowawayRoot::segment().'/vite-asset-tag');
    $files = new Filesystem;
    $files->ensureDirectoryExists($root);
    $files->put($root.'/hot', 'http://assets.example.test:5173');

    // Replace the base test's inert Vite stub with the real URL resolver.
    Vite::swap((new ViteManager)->useHotFile($root.'/hot'));
});

afterEach(function (): void {
    // Clean through a real filesystem, regardless of facade replacements.
    (new Filesystem)->deleteDirectory(
        storage_path('framework/testing/'.ThrowawayRoot::segment().'/vite-asset-tag')
    );
});

it('resolves the supplied source path rather than returning an inert asset stub', function (): void {
    // Supply a non-entry-point asset path through the public tag API.
    $tag = new ViteAsset;
    $tag->setContext([]);
    $tag->setParameters(['src' => 'resources/fonts/firago/FiraGO-Light.woff2']);

    // Preserve the source path on the configured hot-server origin.
    expect($tag->index())->toBe('http://assets.example.test:5173/resources/fonts/firago/FiraGO-Light.woff2');
});

it('emits nothing for an absent or unusable source', function (mixed $source): void {
    // Keep invalid values distinguishable from Vite's non-empty root URL.
    $tag = new ViteAsset;
    $tag->setContext([]);
    $tag->setParameters(['src' => $source]);

    expect($tag->index())->toBe('');
})->with([
    'missing' => [null],
    'empty' => [''],
    'zero string' => ['0'],
    'integer' => [12],
    'boolean' => [true],
    'array' => [['resources/js/app.js']],
]);
