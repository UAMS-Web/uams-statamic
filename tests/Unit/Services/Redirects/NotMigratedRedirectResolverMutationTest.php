<?php

declare(strict_types=1);

use App\Services\Redirects\NotMigratedRedirectResolver;
use Tests\TestCase;

uses(TestCase::class);

covers(NotMigratedRedirectResolver::class);

/**
 * Narrow mutate coverage for NotMigratedRedirectResolver.
 *
 * @command  php artisan test --compact tests/Unit/Services/Redirects/NotMigratedRedirectResolverMutationTest.php
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

it('enumerates proper ancestors nearest-first, excluding root and self', function (): void {
    $resolver = new NotMigratedRedirectResolver;

    expect($resolver->ancestorsOf('/a/b/c'))->toBe(['/a/b', '/a'])
        ->and($resolver->ancestorsOf('/a'))->toBe([])
        ->and($resolver->ancestorsOf('/'))->toBe([])
        ->and($resolver->ancestorsOf('/a/b/c/d'))->toBe(['/a/b/c', '/a/b', '/a']);
});

it('301s a retired permalink to its nearest surviving ancestor with the move note', function (): void {
    $survives = fn (string $path): bool => in_array($path, ['/a/b', '/a'], true);

    $redirect = (new NotMigratedRedirectResolver)->resolve('/a/b/c/d', $survives);

    expect($redirect->response)->toBe('301')
        ->and($redirect->source)->toBe('/a/b/c/d')
        ->and($redirect->target)->toBe('/a/b')
        ->and($redirect->notes)->toBe('Retired in migration; 301 to nearest surviving ancestor.');
});

it('410s when no proper ancestor survives, including top-level paths', function (): void {
    $resolver = new NotMigratedRedirectResolver;
    $none = fn (string $path): bool => false;
    $onlyRoot = fn (string $path): bool => $path === '/';

    $deep = $resolver->resolve('/a/b/c', $none);
    $orphan = $resolver->resolve('/orphan', $onlyRoot);

    expect($deep->response)->toBe('410')
        ->and($deep->source)->toBe('/a/b/c')
        ->and($deep->target)->toBeNull()
        ->and($deep->notes)->toBe('Retired in migration; no surviving ancestor.')
        ->and($orphan->response)->toBe('410')
        ->and($orphan->notes)->toBe('Retired in migration; no surviving ancestor.');
});

it('canonicalizes the retired path before matching ancestors', function (): void {
    $survives = fn (string $path): bool => $path === '/a';

    $redirect = (new NotMigratedRedirectResolver)->resolve('/a/b/', $survives);

    expect($redirect->source)->toBe('/a/b')
        ->and($redirect->target)->toBe('/a')
        ->and($redirect->response)->toBe('301');
});
