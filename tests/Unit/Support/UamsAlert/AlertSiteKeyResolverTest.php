<?php

declare(strict_types=1);

use App\Support\UamsAlert\AlertSiteKeyResolver;
use Illuminate\Config\Repository;

covers(AlertSiteKeyResolver::class);

/**
 * Unit coverage for the UAMS Alert site-bucket resolver (UAMS-Web/uams-statamic-archive#299).
 *
 * The resolver maps a Statamic site handle to its alert bucket via the explicit
 * `config/uamsalert.php` map (many sites → few buckets), defaulting unmapped
 * handles to the configured `default`. Tested with an injected config repository
 * so no app config or content is involved.
 *
 * Mutation: 100% on App\Support\UamsAlert\AlertSiteKeyResolver (8 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Unit/Support/UamsAlert/AlertSiteKeyResolverTest.php
 */

/**
 * Build a resolver over an in-memory `uamsalert` config payload.
 *
 * @param  array<string, mixed>  $config  The `uamsalert` config array
 */
function alertResolver(array $config): AlertSiteKeyResolver
{
    return new AlertSiteKeyResolver(new Repository(['uamsalert' => $config]));
}

it('resolves a handle listed in a bucket to that bucket', function () {
    $resolver = alertResolver([
        'buckets' => ['uamshealth' => ['health', 'health_nicu']],
        'default' => 'uams',
    ]);

    expect($resolver->resolve('health'))->toBe('uamshealth');
    expect($resolver->resolve('health_nicu'))->toBe('uamshealth');
});

it('falls back to the default bucket for an unlisted handle', function () {
    $resolver = alertResolver([
        'buckets' => ['uamshealth' => ['health']],
        'default' => 'uams',
    ]);

    expect($resolver->resolve('academic_affairs'))->toBe('uams');
});

it('matches handles strictly so academic health sites are not bucketed as clinical', function () {
    $resolver = alertResolver([
        'buckets' => ['uamshealth' => ['health']],
        'default' => 'uams',
    ]);

    // `health_literacy` (Center for Health Literacy) is academic, not clinical —
    // strict `in_array` keeps it on the default bucket, unlike a prefix rule.
    expect($resolver->resolve('health_literacy'))->toBe('uams');
});

it('defaults to uams when config is empty or malformed', function () {
    expect(alertResolver([])->resolve('anything'))->toBe('uams');

    // A non-array `buckets` and non-string `default` must not throw or leak.
    expect(alertResolver(['buckets' => 'nope', 'default' => 123])->resolve('x'))->toBe('uams');
});

it('honors a custom default bucket', function () {
    $resolver = alertResolver(['buckets' => [], 'default' => 'inside']);

    expect($resolver->resolve('whatever'))->toBe('inside');
});

it('skips malformed bucket entries (non-string key or non-array handles)', function () {
    $resolver = alertResolver([
        'buckets' => [
            ['health'],                // numeric key 0 → skipped (bucket must be a string)
            'uamshealth' => 'health',  // string handles, not an array → skipped
            'inside' => ['intranet'],  // valid
        ],
        'default' => 'uams',
    ]);

    // A handle present only in the skipped entries falls back to the default …
    expect($resolver->resolve('health'))->toBe('uams');
    // … while the well-formed bucket still resolves.
    expect($resolver->resolve('intranet'))->toBe('inside');
});

it('matches handles by strict type so an int handle never matches a string site', function () {
    // A handle stored as int 1 must not match the string site handle '1'.
    $resolver = alertResolver([
        'buckets' => ['health' => [1]],
        'default' => 'uams',
    ]);

    expect($resolver->resolve('1'))->toBe('uams');
});
