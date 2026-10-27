<?php

declare(strict_types=1);

use App\Tags\Ip;
use App\Tags\Referrer;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Tests HTML escaping for tags that read from superglobals.
 *
 * Mutation: 100% on App\Tags\Ip (3 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Tags/ServerRequestTagsOutputTest.php
 */
afterEach(function () {
    // Reset superglobals to avoid interference with other tests.
    unset(
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_REFERER']
    );
});

it('returns plain ipv4 unchanged from the ip tag', function () {
    // Set a known IP address in the superglobal.
    $_SERVER['REMOTE_ADDR'] = '192.0.2.1';

    // Assert the IP tag returns the known IP address.
    expect((new Ip)->index())->toBe('192.0.2.1');
});

it('escapes html-sensitive characters in the ip tag', function () {
    // Set a malicious IP address in the superglobal.
    $_SERVER['REMOTE_ADDR'] = '"><script>alert(1)</script>';

    // Assert the IP tag returns the escaped IP address.
    expect((new Ip)->index())->toBe(e('"><script>alert(1)</script>'));
});

it('returns a plain https url unchanged from the referrer tag', function () {
    // Set a known referrer URL in the superglobal.
    $_SERVER['HTTP_REFERER'] = 'https://example.com/page';

    // Assert the referrer tag returns the known referrer URL.
    expect((new Referrer)->index())->toBe('https://example.com/page');
});

it('escapes html-sensitive characters in the referrer tag', function () {
    // Set a malicious referrer URL in the superglobal.
    $_SERVER['HTTP_REFERER'] = '"><img src=x onerror=alert(1)>';

    // Assert the referrer tag returns the escaped referrer URL.
    expect((new Referrer)->index())->toBe(e('"><img src=x onerror=alert(1)>'));
});
