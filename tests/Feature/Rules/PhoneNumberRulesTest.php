<?php

declare(strict_types=1);

use App\Rules\InternationalPhone;
use App\Rules\NationalPhone;
use Tests\TestCase;

uses(TestCase::class);

covers(InternationalPhone::class, NationalPhone::class);

/**
 * Behavior of the phone number rules: `InternationalPhone` accepts any valid
 * number once a missing `+` is supplied, and `NationalPhone` accepts a number
 * valid for its configured country, either written nationally or in E.164 form
 * whose country matches. Both reject empty and malformed input, and each builds
 * its own failure message.
 *
 * @command  php artisan test --compact tests/Feature/Rules/PhoneNumberRulesTest.php
 */
it('accepts a valid international number with or without the leading plus', function (mixed $value): void {
    expect((new InternationalPhone)->passes('phone', $value))->toBeTrue();
})->with([
    'U.S. E.164 with separators' => ['+1 501-686-7000'],
    'U.S. without plus' => ['15016867000'],
    'U.K. E.164' => ['+44 20 7946 0958'],
    'U.K. without plus' => ['442079460958'],
    'surrounding whitespace' => ['  +1 501 686 7000  '],
    'integer' => [15016867000],
]);

it('rejects a value that is not a valid international number', function (mixed $value): void {
    expect((new InternationalPhone)->passes('phone', $value))->toBeFalse();
})->with([
    'U.S. national number with no country code' => ['5016867000'],
    'letters' => ['abc'],
    'plus only' => ['+'],
    'country code only' => ['+1'],
    'unassigned country code' => ['+999 1234'],
    'boolean true' => [true],
    'empty string' => [''],
    'null' => [null],
    'string zero' => ['0'],
    'array' => [['+15016867000']],
    'object' => [new stdClass],
]);

it('builds the international failure message', function (): void {
    expect((new InternationalPhone)->message())
        ->toBe('Enter a valid international phone number (e.g., `+1-555-555-5555`).');
});

it('accepts a number valid for the configured country', function (string $country, mixed $value): void {
    expect((new NationalPhone($country))->passes('phone', $value))->toBeTrue();
})->with([
    'U.S. national' => ['US', '5016867000'],
    'U.S. national with separators' => ['US', '(501) 686-7000'],
    'U.S. E.164' => ['US', '+1 501-686-7000'],
    'U.S. E.164 with whitespace' => ['US', ' +15016867000 '],
    'U.S. national integer' => ['US', 5016867000],
    'U.K. national' => ['GB', '020 7946 0958'],
    'U.K. E.164' => ['GB', '+44 20 7946 0958'],
]);

it('defaults to the United States', function (): void {
    expect((new NationalPhone)->passes('phone', '5016867000'))->toBeTrue()
        ->and((new NationalPhone)->passes('phone', '020 7946 0958'))->toBeFalse();
});

it('rejects a number that is not valid for the configured country', function (string $country, mixed $value): void {
    expect((new NationalPhone($country))->passes('phone', $value))->toBeFalse();
})->with([
    'U.K. E.164 for the U.S.' => ['US', '+44 20 7946 0958'],
    'U.S. E.164 for the U.K.' => ['GB', '+1 501-686-7000'],
    'U.K. national for the U.S.' => ['US', '020 7946 0958'],
    'too short' => ['US', '501686'],
    'letters' => ['US', 'abc'],
    'plus only' => ['US', '+'],
    'unknown country code' => ['ZZ', '5016867000'],
    'empty string' => ['US', ''],
    'null' => ['US', null],
    'string zero' => ['US', '0'],
    'array' => ['US', ['5016867000']],
    'whitespace only' => ['US', '   '],
]);

it('names the country and an example in the national failure message', function (string $country, string $expected): void {
    expect((new NationalPhone($country))->message())->toBe($expected);
})->with([
    'United States' => ['US', 'Enter a valid national phone number for the United States of America (e.g., (201) 555-0123).'],
    'United Kingdom' => ['GB', 'Enter a valid national phone number for the United Kingdom (e.g., 0121 234 5678).'],
    'a country without "the"' => ['XK', 'Enter a valid national phone number for Kosovo (e.g., 028 012 345).'],
]);

it('falls back to the bare code when the country is unknown', function (): void {
    expect((new NationalPhone('ZZ'))->message())->toBe('Enter a valid national phone number for ZZ.');
});
