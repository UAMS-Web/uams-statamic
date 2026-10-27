<?php

declare(strict_types=1);

use App\Http\Middleware\SecurityHeaders;
use App\Tags\CspNonce;
use Tests\TestCase;

covers(CspNonce::class);

uses(TestCase::class);

/**
 * Tests for the CspNonce Antlers tag (App\Tags\CspNonce).
 *
 * Mutation: 100% on App\Tags\CspNonce (7 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Tags/CspNonceTagTest.php
 */

/**
 * Build a CspNonce tag instance with empty context and parameters.
 */
function cspNonceTag(): CspNonce
{
    // Instantiate the tag and supply the context/params Statamic expects
    $tag = new CspNonce;
    $tag->setContext([]);
    $tag->setParameters([]);

    return $tag;
}

it('returns the nonce bound by the SecurityHeaders middleware', function () {
    // Bind a known nonce as the middleware would before rendering
    app()->instance(
        SecurityHeaders::NONCE_KEY,
        'TestNonceValue=='
    );

    // Assert the tag echoes the exact bound value
    expect(cspNonceTag()->index())->toBe('TestNonceValue==');
});

it('returns an empty string when no nonce is bound', function () {
    // Ensure no nonce is bound in the container
    app()->forgetInstance(SecurityHeaders::NONCE_KEY);

    // Assert the tag renders a harmless empty value rather than failing
    expect(cspNonceTag()->index())->toBe('');
});

it('string-casts a scalar nonce that is not already a string', function () {
    // Float kills RemoveStringCast: under strict_types a bare float return is a TypeError.
    app()->instance(
        SecurityHeaders::NONCE_KEY,
        1.5
    );

    expect(cspNonceTag()->index())->toBe('1.5');
});

it('returns an empty string when the bound nonce is not scalar', function () {
    // Non-scalar takes the empty-string arm (kills EmptyStringToNotEmpty on that literal).
    app()->instance(
        SecurityHeaders::NONCE_KEY,
        ['not', 'a', 'nonce']
    );

    expect(cspNonceTag()->index())->toBe('');
});
