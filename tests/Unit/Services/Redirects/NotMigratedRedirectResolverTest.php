<?php

declare(strict_types=1);

use App\Services\Redirects\NotMigratedRedirectResolver;

/**
 * Unit tests for NotMigratedRedirectResolver (UAMS-Web/uams-statamic-archive#1641).
 *
 * Pins the retired-permalink disposition rule: a `301` to the nearest surviving
 * ancestor, else a `410 Gone` — and the deliberate exclusion of the site root as
 * an ancestor, so a retired deep link with no surviving intermediate context is
 * honestly declared gone rather than funnelled to the home page.
 *
 * @command  php artisan test --compact tests/Unit/Services/Redirects/NotMigratedRedirectResolverTest.php
 */
/**
 * A fresh resolver (pure, so a new instance per test is free).
 */
function migrationResolver(): NotMigratedRedirectResolver
{
    return new NotMigratedRedirectResolver;
}

it('enumerates proper ancestors nearest-first, excluding root and self', function (): void {
    expect(migrationResolver()->ancestorsOf('/a/b/c'))->toBe(['/a/b', '/a']);
});

it('returns no ancestors for a top-level path', function (): void {
    expect(migrationResolver()->ancestorsOf('/a'))->toBe([])
        ->and(migrationResolver()->ancestorsOf('/'))->toBe([]);
});

it('301s a retired permalink to its nearest surviving ancestor', function (): void {
    // Only /a/b survives; /a/b/c/d should land on it, not the further /a.
    $survives = fn (string $path): bool => \in_array($path, ['/a/b', '/a'], true);

    $redirect = migrationResolver()->resolve('/a/b/c/d', $survives);

    expect($redirect->response)->toBe('301')
        ->and($redirect->source)->toBe('/a/b/c/d')
        ->and($redirect->target)->toBe('/a/b');
});

it('410s a retired permalink when no proper ancestor survives', function (): void {
    // Nothing survives — not even were the root offered, it is never an ancestor.
    $survives = fn (string $path): bool => false;

    $redirect = migrationResolver()->resolve('/a/b/c', $survives);

    expect($redirect->response)->toBe('410')
        ->and($redirect->source)->toBe('/a/b/c')
        ->and($redirect->target)->toBeNull();
});

it('410s a top-level retired permalink rather than redirecting to the home page', function (): void {
    // The home page always "exists", but the root is never treated as an ancestor,
    // so a lone top-level retired path is gone, not a soft-404 to /.
    $survives = fn (string $path): bool => $path === '/';

    $redirect = migrationResolver()->resolve('/orphan', $survives);

    expect($redirect->response)->toBe('410');
});

it('canonicalizes the retired path before matching ancestors', function (): void {
    $survives = fn (string $path): bool => $path === '/a';

    $redirect = migrationResolver()->resolve('/a/b/', $survives);

    expect($redirect->source)->toBe('/a/b')
        ->and($redirect->target)->toBe('/a');
});
