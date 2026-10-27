<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Statamic\Statamic;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Ensures the CP Vite bundle is registered once with hot file and build directory.
 *
 * Mutation: 100% on App\Providers\AppServiceProvider (10 mutants, --covered-only).
 */
it('registers the cp vite bundle with hot file and build directory', function (): void {
    // AppServiceProvider must register a single `app` Vite config so hot file and
    // build directory are not overwritten by a duplicate registration.
    $vites = Statamic::availableVites(Request::create('/'));

    expect($vites)->toHaveKey('app');

    /** @var array<string, mixed> $vites */
    $app = $vites['app'];
    expect($app)->toBeArray();

    /** @var array<string, mixed> $app */
    expect($app['hotFile'])->toBe(public_path('cp-hot'));

    expect($app['buildDirectory'])->toBe('vendor/app');

    expect($app['input'])->toBe([
        'resources/js/cp.js',
        'resources/css/tailwind/cp.css',
    ]);
});
