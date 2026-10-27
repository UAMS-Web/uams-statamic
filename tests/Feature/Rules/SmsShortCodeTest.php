<?php

declare(strict_types=1);

use App\Rules\SmsShortCode;
use Tests\TestCase;

uses(TestCase::class);

covers(SmsShortCode::class);

/**
 * Behavior of the U.S. SMS short code rule (`SmsShortCode`): five or six digits
 * whose first digit is `0` or `2`-`9`, with nothing before or after them.
 *
 * @command  php artisan test --compact tests/Feature/Rules/SmsShortCodeTest.php
 */

/**
 * Run the short code rule and return every message it failed with.
 *
 * @return list<mixed>
 */
function smsShortCodeMessages(mixed $value): array
{
    $messages = [];

    (new SmsShortCode)->validate('short_code', $value, function (mixed $message) use (&$messages): void {
        $messages[] = $message;
    });

    return $messages;
}

it('accepts a five or six digit short code that does not start with 1', function (mixed $value): void {
    expect(smsShortCodeMessages($value))->toBe([])
        ->and(validator(['code' => $value], ['code' => [new SmsShortCode]])->passes())->toBeTrue();
})->with([
    'five digits from 0' => ['01234'],
    'six digits from 0' => ['012345'],
    'five digits from 2' => ['23456'],
    'six digits from 2' => ['234567'],
    'five digits from 9' => ['99999'],
    'integer' => [23456],
]);

it('rejects a value that is not a U.S. short code', function (mixed $value): void {
    expect(smsShortCodeMessages($value))->toBe([__('statamic::validation.sms_short_code')]);
})->with([
    'starts with 1, five digits' => ['12345'],
    'starts with 1, six digits' => ['123456'],
    'four digits' => ['0123'],
    'seven digits' => ['0123456'],
    'letter' => ['0123a'],
    'leading space' => [' 01234'],
    'trailing space' => ['01234 '],
    'trailing newline' => ["01234\n"],
    'hyphenated' => ['012-34'],
    'integer starting with 1' => [12345],
]);

it('leaves empty input to other rules', function (mixed $value): void {
    expect(smsShortCodeMessages($value))->toBe([]);
})->with([
    'null' => [null],
    'empty string' => [''],
    'string zero' => ['0'],
]);
