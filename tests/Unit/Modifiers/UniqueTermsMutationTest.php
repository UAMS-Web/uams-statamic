<?php

declare(strict_types=1);

use App\Modifiers\UniqueTerms;
use Tests\TestCase;

uses(TestCase::class);

covers(UniqueTerms::class);

/**
 * Narrow mutate coverage for UniqueTerms.
 *
 * @command  php artisan test --compact tests/Unit/Modifiers/UniqueTermsMutationTest.php
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

it('deduplicates id()-bearing objects and keeps later distinct items after a duplicate', function (): void {
    // ContinueToBreak on the duplicate arm would drop $beta.
    $alpha = new class
    {
        public function id(): string
        {
            return 'subject_matter::alpha';
        }
    };
    $alphaAgain = new class
    {
        public function id(): string
        {
            return 'subject_matter::alpha';
        }
    };
    $beta = new class
    {
        public function id(): string
        {
            return 'subject_matter::beta';
        }
    };

    expect((new UniqueTerms)->index([$alpha, $alphaAgain, $beta], [], []))->toBe([$alpha, $beta]);
});

it('deduplicates scalars and null by value', function (): void {
    expect((new UniqueTerms)->index(['a', 'b', 'a', null, null, 1, 1], [], []))
        ->toBe(['a', 'b', null, 1]);
});

it('deduplicates id-less objects by instance identity', function (): void {
    $plainA = new stdClass;
    $plainB = new stdClass;

    expect((new UniqueTerms)->index([$plainA, $plainA, $plainB], [], []))->toBe([$plainA, $plainB]);
});

it('string-casts object ids before comparing', function (): void {
    // RemoveStringCast leaves a float array key (coerced to int 1) that no
    // longer collides with the string "1.5".
    $asFloat = new class
    {
        public function id(): float
        {
            return 1.5;
        }
    };
    $asString = new class
    {
        public function id(): string
        {
            return '1.5';
        }
    };

    expect((new UniqueTerms)->index([$asFloat, $asString], [], []))->toBe([$asFloat]);
});

it('returns an empty array for empty input', function (): void {
    expect((new UniqueTerms)->index([], [], []))->toBe([]);
});
