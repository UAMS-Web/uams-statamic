<?php

declare(strict_types=1);

use App\Fieldtypes\Phone;
use App\Rules\InternationalPhone;
use App\Rules\NationalPhone;
use App\Rules\SmsShortCode;
use App\Services\PhoneUtil;
use Statamic\Fields\Field;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @covers \App\Fieldtypes\Phone
 *
 * @param  array<string, mixed>  $config
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/PhoneFieldtypeTest.php
 */
function makePhone(array $config = []): Phone
{
    $fieldtype = new Phone;
    $fieldtype->setField(new Field('phone', array_merge(['type' => 'phone'], $config)));

    return $fieldtype;
}

it('selects the validation rule matching the configured preset', function (string $preset, string $ruleClass): void {
    // Compared by exact class rather than instanceof: the rule set is a closed union
    // PHPStan already knows, so an instanceof check over it cannot fail — and the
    // preset must select precisely this rule, not merely a subclass of it.
    expect(makePhone(['preset' => $preset])->rules()[0]::class)->toBe($ruleClass);
})->with([
    'international' => ['tel', InternationalPhone::class],
    'national' => ['tel-national', NationalPhone::class],
    'sms short code' => ['sms-short-code', SmsShortCode::class],
]);

it('defaults to the national phone rule when no preset is configured', function (): void {
    expect(makePhone()->rules()[0])->toBeInstanceOf(NationalPhone::class);
});

it('produces no rules for an unrecognized preset', function (): void {
    expect(makePhone(['preset' => 'nonsense'])->rules())->toBe([]);
});

it('normalizes empty-ish input to an empty string', function (mixed $input): void {
    expect((new Phone)->normalizeValue($input, ['preset' => 'tel-national', 'country' => 'US']))->toBe('');
})->with([
    'empty' => [''],
    'whitespace' => ['   '],
    'zero' => ['0'],
]);

it('normalizes a national number to E.164 using the configured country', function (): void {
    expect((new Phone)->normalizeValue('(479) 555-0100', ['preset' => 'tel-national', 'country' => 'US']))
        ->toBe('+14795550100');
});

it('normalizes an international number to E.164', function (): void {
    expect((new Phone)->normalizeValue('+1 479 555 0100', ['preset' => 'tel']))
        ->toBe('+14795550100');
});

it('cleans an sms short code without E.164 formatting', function (): void {
    expect((new Phone)->normalizeValue('12345', ['preset' => 'sms-short-code']))->toBe('12345');
});

it('returns the original value when a phone number cannot be parsed', function (): void {
    // A libphonenumber parse failure is caught and the raw input returned (logged,
    // never thrown), so an unparseable submission never 500s the save.
    $unparseable = 'zzzzzzzz';

    expect((new Phone)->normalizeValue($unparseable, ['preset' => 'tel']))->toBe($unparseable);
});

it('formats a value for display via the national format for tel-national', function (): void {
    // The fieldtype must pick the national display format for the national preset —
    // comparing against PhoneUtil with 'national' fails if the branch selects another.
    expect((new Phone)->formatValueForDisplay('+14795550100', ['preset' => 'tel-national']))
        ->toBe(PhoneUtil::formatForDisplay('+14795550100', 'national'));
});

it('formats a value for display via the international format for tel', function (): void {
    // The international preset must pick the international display format; paired with
    // the national test (the two formats differ), this pins the format-selection
    // branch — a mutation forcing one format unconditionally breaks one of the two.
    expect((new Phone)->formatValueForDisplay('+14795550100', ['preset' => 'tel']))
        ->toBe(PhoneUtil::formatForDisplay('+14795550100', 'international'));
});

it('passes an sms short code through display formatting unchanged', function (): void {
    expect((new Phone)->formatValueForDisplay('12345', ['preset' => 'sms-short-code']))->toBe('12345');
});
