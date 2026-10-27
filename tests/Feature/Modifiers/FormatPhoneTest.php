<?php

declare(strict_types=1);

use App\Modifiers\FormatPhone;
use Tests\TestCase;

uses(TestCase::class);

covers(FormatPhone::class);

/**
 * Mutation: tracked under App\Modifiers\FormatPhone (--covered-only).
 *
 * @command  php artisan test --compact tests/Feature/Modifiers/FormatPhoneTest.php
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

it('formats E.164 phone numbers for display', function (mixed $value, array $params, string $expected) {
    expect((new FormatPhone)->index($value, $params, []))->toBe($expected);
})->with([
    'US national (default)' => ['+15016868998', [], '501-686-8998'],
    'US national explicit' => ['+15016868998', ['national'], '501-686-8998'],
    'US international' => ['+15016868998', ['international'], '+1 501-686-8998'],
    'mode is case-insensitive' => ['+15016868998', ['INTERNATIONAL'], '+1 501-686-8998'],
    'unknown mode falls back to national' => ['+15016868998', ['postal'], '501-686-8998'],
    'empty string yields empty' => ['', [], ''],
    'whitespace-only yields empty' => ['   ', [], ''],
    'non-scalar yields empty' => [[], [], ''],
    'integer scalar is stringified then left unparsed' => [15016868998, [], '15016868998'],
    'non-scalar mode falls back to national' => ['+15016868998', [['international']], '501-686-8998'],
    'integer mode is stringified then falls back' => ['+15016868998', [1], '501-686-8998'],
]);
