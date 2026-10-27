<?php

declare(strict_types=1);

use App\Services\PhoneUtil;
use Illuminate\Support\Facades\Log;
use Mockery\LegacyMockInterface;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @covers \App\Services\PhoneUtil
 *
 * Display formatting and example numbers, against the installed libphonenumber metadata.
 * The `catch` in `generateExamplePhoneNumber()` is not exercised: the library returns null
 * for an unknown region rather than throwing, and no supported input makes it throw.
 *
 * @command  php artisan test --compact tests/Feature/Services/PhoneUtilTest.php
 */
it('formats a U.S. number nationally in AP style', function (): void {
    expect(PhoneUtil::formatForDisplay('+14795550100'))->toBe('479-555-0100')
        ->and(PhoneUtil::formatForDisplay('+14795550100', 'national'))->toBe('479-555-0100');
});

it('formats a number outside the U.S. in its own national style', function (): void {
    expect(PhoneUtil::formatForDisplay('+442079460958', 'national'))->toBe('020 7946 0958');
});

it('formats internationally on request, and for any other format name', function (string $format): void {
    expect(PhoneUtil::formatForDisplay('+14795550100', $format))->toBe('+1 479-555-0100');
})->with(['international', 'e164', '']);

it('returns the input unchanged when it cannot be parsed', function (string $input): void {
    expect(PhoneUtil::formatForDisplay($input))->toBe($input)
        ->and(PhoneUtil::formatForDisplay($input, 'international'))->toBe($input);
})->with(['not a phone', '+1']);

it('gives an example number for a region, in either format', function (): void {
    expect(PhoneUtil::exampleNational())->toBe('(201) 555-0123')
        ->and(PhoneUtil::exampleInternational())->toBe('+1 201-555-0123')
        ->and(PhoneUtil::exampleNational('GB'))->toBe('0121 234 5678');
});

it('gives no example number for a region the library does not know, without logging', function (string $region): void {
    /** @var LegacyMockInterface $log */
    $log = Log::spy();

    expect(PhoneUtil::exampleNational($region))->toBeNull()
        ->and(PhoneUtil::exampleInternational($region))->toBeNull();

    $log->shouldNotHaveReceived('error');
})->with(['ZZ', '']);

it('adds a plus to a number that has none', function (string $input, string $expected): void {
    expect(PhoneUtil::addPlusIfMissing($input))->toBe($expected);
})->with([
    'digits only' => ['14795550100', '+14795550100'],
    'already has a plus' => ['+14795550100', '+14795550100'],
    'a plus later in the string' => ['1+479', '+1+479'],
    'a plus at the end' => ['1479+', '+1479+'],
    'just a plus' => ['+', '+'],
    'empty' => ['', ''],
]);

it('trims input before validating it', function (): void {
    expect(PhoneUtil::cleanInput("  +1 479 555 0100 \n"))->toBe('+1 479 555 0100');
});
