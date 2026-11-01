<?php

declare(strict_types=1);

use App\Rules\BaseRules\BaseValidationRule;
use App\Rules\GlobalLocationNumber;
use App\Rules\Gs1Modulo10;
use App\Rules\LatLng;
use App\Rules\Luhn;
use App\Rules\Name;
use App\Rules\Pmid;
use App\Services\PubMed\PubMedEsummaryService;
use Tests\TestCase;

uses(TestCase::class);

covers(GlobalLocationNumber::class, Gs1Modulo10::class, LatLng::class, Luhn::class, Name::class, Pmid::class);

/**
 * Behavior of the rules that check more than one pattern: the two GS1 modulo-10
 * rules and the Luhn rule (format first, then the check digit, with expected
 * values taken from the published algorithms rather than from the rule), the
 * PubMed identifier rule (format first, then the PubMed lookup, doubled here),
 * the latitude/longitude pair rule, and the personal name rule.
 *
 * @command  php artisan test --compact tests/Feature/Rules/CheckedIdentifierRulesTest.php
 */

/**
 * Run a rule and return every message it failed with.
 *
 * @return list<mixed>
 */
function checkedIdentifierMessages(BaseValidationRule $rule, mixed $value): array
{
    $messages = [];

    $rule->validate('identifier', $value, function (mixed $message) use (&$messages): void {
        $messages[] = $message;
    });

    return $messages;
}

/**
 * Thirteen-digit numbers whose GS1 modulo-10 check digit is right, including one
 * whose weighted sum is a multiple of ten so its check digit is zero.
 *
 * @return array<string, array{string}>
 */
function checkedIdentifierGs1Valid(): array
{
    return [
        'published EAN-13' => ['4006381333931'],
        'published GLN' => ['0614141000005'],
        'sequence' => ['1234567890128'],
        'all zeros' => ['0000000000000'],
        'all nines' => ['9999999999994'],
        'check digit zero' => ['5012345678900'],
    ];
}

/**
 * Thirteen-digit numbers whose GS1 modulo-10 check digit is wrong.
 *
 * @return array<string, array{string}>
 */
function checkedIdentifierGs1Invalid(): array
{
    return [
        'check digit one low' => ['4006381333930'],
        'check digit one high' => ['4006381333932'],
        'first two digits swapped' => ['2134567890128'],
        'zero check digit expected, one given' => ['5012345678901'],
    ];
}

/**
 * Values that are not thirteen digits.
 *
 * @return array<string, array{string}>
 */
function checkedIdentifierGs1Malformed(): array
{
    return [
        'twelve digits' => ['400638133393'],
        'fourteen digits' => ['40063813339310'],
        'letter' => ['400638133393A'],
        'trailing newline' => ["4006381333931\n"],
    ];
}

it('accepts a GS1 number with the right check digit', function (string $value): void {
    expect(checkedIdentifierMessages(new Gs1Modulo10, $value))->toBe([])
        ->and(checkedIdentifierMessages(new GlobalLocationNumber, $value))->toBe([])
        ->and(Gs1Modulo10::isValidGs1Modulo10($value))->toBeTrue();
})->with(checkedIdentifierGs1Valid());

it('rejects a GS1 number with the wrong check digit', function (string $value): void {
    expect(checkedIdentifierMessages(new Gs1Modulo10, $value))->toBe([__('statamic::validation.gs1_modulo_10.check_digit_failed')])
        ->and(checkedIdentifierMessages(new GlobalLocationNumber, $value))->toBe([__('statamic::validation.global_location_number.invalid')])
        ->and(Gs1Modulo10::isValidGs1Modulo10($value))->toBeFalse();
})->with(checkedIdentifierGs1Invalid());

it('rejects a value that is not thirteen digits before checking the digit', function (string $value): void {
    expect(checkedIdentifierMessages(new Gs1Modulo10, $value))->toBe([__('statamic::validation.gs1_modulo_10.format')])
        ->and(checkedIdentifierMessages(new GlobalLocationNumber, $value))->toBe([__('statamic::validation.global_location_number.format')]);
})->with(checkedIdentifierGs1Malformed());

it('accepts a number that passes the Luhn check', function (string $value): void {
    expect(checkedIdentifierMessages(new Luhn, $value))->toBe([])
        ->and(Luhn::isValidLuhn($value))->toBeTrue();
})->with([
    'published example' => ['79927398713'],
    'card-shaped' => ['4111111111111111'],
    'single zero' => ['00'],
    'doubled digit over nine' => ['59'],
    'odd length' => ['49927398716'],
]);

it('doubles each digit in a doubled position to its Luhn contribution', function (string $value): void {
    expect(Luhn::isValidLuhn($value))->toBeTrue()
        ->and(Luhn::isValidLuhn($value[0].(((int) $value[1] + 1) % 10)))->toBeFalse();
})->with([
    'zero' => ['00'],
    'one' => ['18'],
    'two' => ['26'],
    'three' => ['34'],
    'four' => ['42'],
    'five' => ['59'],
    'six' => ['67'],
    'seven' => ['75'],
    'eight' => ['83'],
    'nine' => ['91'],
]);

it('rejects a number that fails the Luhn check', function (string $value): void {
    expect(checkedIdentifierMessages(new Luhn, $value))->toBe([__('statamic::validation.luhn.check_failed')])
        ->and(Luhn::isValidLuhn($value))->toBeFalse();
})->with([
    'published example, last digit changed' => ['79927398710'],
    'card-shaped, last digit changed' => ['4111111111111112'],
    'doubled digit not reduced' => ['58'],
    'two digits swapped' => ['97927398713'],
]);

it('rejects a Luhn value holding anything but digits before checking it', function (string $value): void {
    expect(checkedIdentifierMessages(new Luhn, $value))->toBe([__('statamic::validation.luhn.format')]);
})->with([
    'hyphen' => ['7992-7398713'],
    'space' => [' 79927398713'],
    'letter' => ['7992739871A'],
    'trailing newline' => ["79927398713\n"],
]);

it('checks the Luhn digit over the prefix and suffix it is given', function (?string $prepend, ?string $append, string $value, array $expected): void {
    $expected = array_map(static fn (mixed $key): string => __(mustBeString($key, 'message key')), $expected);

    expect(checkedIdentifierMessages(new Luhn($prepend, $append), $value))->toBe($expected);
})->with([
    'prefix completes the number' => ['80840', null, '1234567893', []],
    'prefix, wrong check digit' => ['80840', null, '1234567892', ['statamic::validation.luhn.check_failed']],
    'suffix completes the number' => [null, '3', '80840123456789', []],
    'prefix and suffix complete the number' => ['80840', '3', '123456789', []],
    'prefix and suffix, wrong digit' => ['80840', '3', '123456788', ['statamic::validation.luhn.check_failed']],
    'without the prefix the number fails' => [null, null, '1234567893', ['statamic::validation.luhn.check_failed']],
    'a prefix that is not digits' => ['A', null, '1', ['statamic::validation.luhn.format']],
    'a suffix that is not digits' => [null, '-', '1', ['statamic::validation.luhn.format']],
]);

it('accepts a PMID the PubMed lookup finds', function (): void {
    $this->mock(PubMedEsummaryService::class)->shouldReceive('pmidExists')->once()->with('12345678')->andReturnTrue();

    expect(checkedIdentifierMessages(new Pmid, '12345678'))->toBe([]);
});

it('rejects a well-formed PMID the PubMed lookup does not find', function (): void {
    $this->mock(PubMedEsummaryService::class)->shouldReceive('pmidExists')->once()->with('1')->andReturnFalse();

    expect(checkedIdentifierMessages(new Pmid, '1'))->toBe([__('statamic::validation.pubmed.pmid.unverified')]);
});

it('rejects a malformed PMID without looking it up', function (string $value): void {
    $this->mock(PubMedEsummaryService::class)->shouldNotReceive('pmidExists');

    expect(checkedIdentifierMessages(new Pmid, $value))->toBe([__('statamic::validation.pubmed.pmid.format')]);
})->with([
    'leading zero' => ['0123'],
    'zeros' => ['00'],
    'nine digits' => ['123456789'],
    'letter' => ['12a'],
    'trailing newline' => ["123\n"],
]);

it('accepts a comma-separated latitude and longitude', function (string $value): void {
    expect(checkedIdentifierMessages(new LatLng, $value))->toBe([]);
})->with([
    'plain' => ['37.7749,-122.4194'],
    'spaces around the comma and the pair' => [' 37.7749 , -122.4194 '],
    'bounds' => ['-90,180'],
    'zeros' => ['0,0'],
]);

it('rejects a value that is not exactly two comma-separated parts', function (string $value): void {
    expect(checkedIdentifierMessages(new LatLng, $value))->toBe([__('statamic::validation.lat_lng.comma_separated')]);
})->with([
    'one number' => ['37.7749'],
    'three numbers' => ['1,2,3'],
    'semicolon' => ['37.7749;-122.4194'],
    'empty latitude' => [',-122.4194'],
    'empty longitude' => ['37.7749,'],
    'a bare comma' => [' , '],
]);

it('reports each coordinate out of range or not a number, latitude first', function (string $value, array $expected): void {
    $expected = array_map(static fn (mixed $key): string => __(mustBeString($key, 'message key')), $expected);

    expect(checkedIdentifierMessages(new LatLng, $value))->toBe($expected);
})->with([
    'latitude out of range' => ['91,0', ['statamic::validation.latitude']],
    'longitude out of range' => ['0,181', ['statamic::validation.longitude']],
    'both out of range' => ['91,181', ['statamic::validation.latitude', 'statamic::validation.longitude']],
    'latitude not a number' => ['north,0', ['statamic::validation.decimal_valid']],
]);

it('accepts a name of letters, accented letters, spaces, hyphens and apostrophes', function (string $value): void {
    expect(checkedIdentifierMessages(new Name, $value))->toBe([]);
})->with([
    'plain' => ['Smith'],
    'accented' => ['José'],
    'accented first letter' => ['Ölaf'],
    'apostrophe' => ["O'Brien"],
    'hyphen' => ['Mary-Jane'],
    'space' => ['Ann Marie'],
    'hyphen and apostrophe' => ["Jean-Luc O'Connor"],
]);

it('accepts up to two initials opening a name', function (string $value): void {
    expect(checkedIdentifierMessages(new Name, $value))->toBe([]);
})->with([
    'one initial' => ['J. Smith'],
    'two initials' => ['J.R. Smith'],
    'two initials and no space' => ['J.R.Smith'],
    'one initial and no space' => ['J.Smith'],
    'a space between initials' => ['J. R. Smith'],
    'accented initial' => ['É. Smith'],
    'before a compound name' => ["J.R. O'Connor-Smith"],
]);

it('rejects a period that is not part of an opening initial', function (string $value): void {
    expect(checkedIdentifierMessages(new Name, $value))->toBe([__('statamic::validation.name')]);
})->with([
    'leading period' => ['.Smith'],
    'two leading periods' => ['..Smith'],
    'doubled period' => ['J..Smith'],
    'trailing suffix' => ['Smith Jr.'],
    'between names' => ['John.Doe'],
    'after two letters' => ['Jo. Smith'],
    'three initials' => ['J.R.T. Smith'],
    'initials alone' => ['J.R.'],
    'space before the period' => ['J . Smith'],
    'two spaces after the initial' => ['J.  Smith'],
    'mid name' => ['Smith J. Jones'],
]);

it('rejects a name with a digit, an outer space or a doubled separator', function (string $value): void {
    expect(checkedIdentifierMessages(new Name, $value))->toBe([__('statamic::validation.name')]);
})->with([
    'digit' => ['Smith1'],
    'leading space' => [' Smith'],
    'trailing space' => ['Smith '],
    'doubled hyphen' => ['Mary--Jane'],
    'symbol' => ['Smith!'],
]);
