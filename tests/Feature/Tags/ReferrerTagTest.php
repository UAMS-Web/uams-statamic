<?php

declare(strict_types=1);

use App\Support\Forms\FormRequestContext;
use App\Tags\Referrer;
use Tests\TestCase;

covers(Referrer::class);

uses(TestCase::class);

/**
 * Tests for the Referrer Antlers tag (App\Tags\Referrer).
 *
 * Mutation: tracked under App\Tags\Referrer (--covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Tags/ReferrerTagTest.php
 */
$savedReferer = null;

beforeEach(function () use (&$savedReferer): void {
    $savedReferer = $_SERVER['HTTP_REFERER'] ?? null;
});

afterEach(function () use (&$savedReferer): void {
    if ($savedReferer === null) {
        unset($_SERVER['HTTP_REFERER']);
    } else {
        $_SERVER['HTTP_REFERER'] = $savedReferer;
    }
});

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

it('returns empty string when HTTP_REFERER is absent', function (): void {
    unset($_SERVER['HTTP_REFERER']);

    $tag = new Referrer;
    $tag->setContext([]);
    $tag->setParameters([]);

    expect($tag->index())->toBe('');
});

it('escapes html special characters from the live referrer', function (): void {
    $_SERVER['HTTP_REFERER'] = 'https://example.com/<script>';

    $tag = new Referrer;
    $tag->setContext([]);
    $tag->setParameters([]);

    expect($tag->index())->toBe('https://example.com/&lt;script&gt;');
});

it('coerces a non-string HTTP_REFERER to an empty string', function (): void {
    $_SERVER['HTTP_REFERER'] = ['not', 'a', 'string'];

    $tag = new Referrer;
    $tag->setContext([]);
    $tag->setParameters([]);

    expect($tag->index())->toBe('');
});

it('prefers the captured form-email submitter referrer over live HTTP_REFERER', function (): void {
    $_SERVER['HTTP_REFERER'] = 'https://worker.example/';

    $captured = new FormRequestContext(
        '203.0.113.10',
        'https://submitter.example/<x>',
        [],
        'default',
    );

    $tag = new Referrer;
    $tag->setContext([]);
    $tag->setParameters([]);

    expect($captured->during(fn () => $tag->index()))->toBe('https://submitter.example/&lt;x&gt;');
});
