<?php

declare(strict_types=1);

use App\Support\Forms\FormRequestContext;
use App\Tags\Ip;
use Tests\TestCase;

covers(Ip::class);

uses(TestCase::class);

/**
 * Tests for the Ip Antlers tag (App\Tags\Ip).
 *
 * Mutation: 100% on App\Tags\Ip (7 mutants, --covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Tags/IpTagTest.php
 */
$savedRemoteAddr = null;

beforeEach(function () use (&$savedRemoteAddr) {
    $savedRemoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;
});

afterEach(function () use (&$savedRemoteAddr) {
    if ($savedRemoteAddr === null) {
        unset($_SERVER['REMOTE_ADDR']);
    } else {
        $_SERVER['REMOTE_ADDR'] = $savedRemoteAddr;
    }
});

it('returns empty string when remote addr absent', function () {
    unset($_SERVER['REMOTE_ADDR']);

    $tag = new Ip;
    $tag->setContext([]);
    $tag->setParameters([]);

    expect($tag->index())->toBe('');
});

it('escapes html special characters', function () {
    $_SERVER['REMOTE_ADDR'] = '192.0.2.1<script>';

    $tag = new Ip;
    $tag->setContext([]);
    $tag->setParameters([]);

    expect($tag->index())->toBe('192.0.2.1&lt;script&gt;');
});

it('prefers the captured form-email submitter IP over live REMOTE_ADDR', function () {
    // InstanceOfToFalse on FormRequestContext::active() would fall through to REMOTE_ADDR.
    $_SERVER['REMOTE_ADDR'] = '192.0.2.99';

    $captured = new FormRequestContext(
        '203.0.113.10<script>',
        '',
        [],
        'default',
    );

    $tag = new Ip;
    $tag->setContext([]);
    $tag->setParameters([]);

    expect($captured->during(fn () => $tag->index()))->toBe('203.0.113.10&lt;script&gt;');
});
