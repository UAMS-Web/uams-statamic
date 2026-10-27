<?php

declare(strict_types=1);

use App\Support\Statamic\Assets\CreditTextResolver;
use Tests\TestCase;

uses(TestCase::class);

covers(CreditTextResolver::class);

/**
 * Unit tests for asset credit text joining rules.
 *
 * Mutation: tracked under App\Support\Statamic\Assets\CreditTextResolver
 * (--covered-only).
 *
 * @command  php artisan test --compact tests/Unit/Support/Statamic/Assets/CreditTextResolverTest.php
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

it('returns null when both are missing', function () {
    expect(CreditTextResolver::resolve(null, null))->toBeNull();
});

it('returns null when both are empty strings', function () {
    expect(CreditTextResolver::resolve('', '   '))->toBeNull();
});

it('returns only the creator when the organization is absent', function () {
    expect(CreditTextResolver::resolve('  Jane Doe  ', null))->toBe('Jane Doe');
});

it('returns only the organization when the creator is absent', function () {
    expect(CreditTextResolver::resolve(null, 'UAMS'))->toBe('UAMS');
});

it('joins with a separator when both are present', function () {
    expect(CreditTextResolver::resolve('Jane Doe', 'UAMS'))->toBe('Jane Doe / UAMS');
});

it('ignores non-string values', function () {
    expect(CreditTextResolver::resolve(1, ['x']))->toBeNull();
    expect(CreditTextResolver::resolve('Solo', 99))->toBe('Solo');
});
