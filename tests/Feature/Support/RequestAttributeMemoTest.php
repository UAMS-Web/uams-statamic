<?php

declare(strict_types=1);

use App\Support\RequestAttributeMemo;
use Tests\TestCase;

uses(TestCase::class);

covers(RequestAttributeMemo::class);

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

/**
 * Mutation coverage for App\Support\RequestAttributeMemo.
 *
 * @command  php artisan test --compact tests/Feature/Support/RequestAttributeMemoTest.php
 */
it('caches a non-empty value so a second call does not re-invoke the factory', function (): void {
    $bagAttribute = '_test_request_attribute_memo_nonempty';
    $invocations = 0;

    $factory = static function () use (&$invocations): string {
        $invocations++;

        return 'computed';
    };

    $first = RequestAttributeMemo::remember($bagAttribute, 'key', $factory);
    $second = RequestAttributeMemo::remember($bagAttribute, 'key', $factory);

    expect($first)->toBe('computed')
        ->and($second)->toBe('computed')
        ->and($invocations)->toBe(1);
});

it('caches an empty string (array_key_exists semantics)', function (): void {
    $bagAttribute = '_test_request_attribute_memo_empty_string';
    $invocations = 0;

    $factory = static function () use (&$invocations): string {
        $invocations++;

        return '';
    };

    RequestAttributeMemo::remember($bagAttribute, 'empty', $factory);
    RequestAttributeMemo::remember($bagAttribute, 'empty', $factory);

    expect($invocations)->toBe(1);
});

it('caches null without treating it as a miss', function (): void {
    $bagAttribute = '_test_request_attribute_memo_null';
    $invocations = 0;

    $factory = static function () use (&$invocations): mixed {
        $invocations++;

        return null;
    };

    expect(RequestAttributeMemo::remember($bagAttribute, 'nil', $factory))->toBeNull()
        ->and(RequestAttributeMemo::remember($bagAttribute, 'nil', $factory))->toBeNull()
        ->and($invocations)->toBe(1);
});

it('replaces a non-array bag attribute on write', function (): void {
    $bagAttribute = '_test_request_attribute_memo_corrupt';
    request()->attributes->set($bagAttribute, 'not-an-array');

    $value = RequestAttributeMemo::remember($bagAttribute, 'k', static fn (): string => 'fresh');

    expect($value)->toBe('fresh')
        ->and(request()->attributes->get($bagAttribute))->toBe(['k' => 'fresh']);
});

it('clears the bag on forget so the next remember runs the factory again', function (): void {
    $bagAttribute = '_test_request_attribute_memo_forget';
    $invocations = 0;

    $factory = static function () use (&$invocations): string {
        $invocations++;

        return 'v1';
    };

    RequestAttributeMemo::remember($bagAttribute, 'k', $factory);
    RequestAttributeMemo::forget($bagAttribute);
    RequestAttributeMemo::remember($bagAttribute, 'k', $factory);

    expect($invocations)->toBe(2);
});
