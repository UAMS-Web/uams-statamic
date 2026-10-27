<?php

declare(strict_types=1);

use App\Rules\Npi;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

covers(Npi::class);

/**
 * Behavior of the National Provider Identifier rule (`Npi`): the ten-digit
 * format gate, the Luhn check digit over the `80840` prefix, and the NPPES
 * registry lookup (a failed request, no matching record, a match). HTTP is faked,
 * so the registry is never contacted.
 *
 * @command  php artisan test --compact tests/Feature/Rules/NpiTest.php
 */
const NPI_RULE_VALID = '1234567893';

/**
 * Run the NPI rule and return every message it failed with.
 *
 * @return list<mixed>
 */
function npiRuleMessages(mixed $value): array
{
    $messages = [];

    (new Npi)->validate('npi', $value, function (mixed $message) use (&$messages): void {
        $messages[] = $message;
    });

    return $messages;
}

it('accepts an NPI the registry returns a record for', function (): void {
    Http::fake(['npiregistry.cms.hhs.gov/*' => Http::response(['result_count' => 1], 200)]);

    expect(npiRuleMessages(NPI_RULE_VALID))->toBe([]);

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && str_starts_with($request->url(), 'https://npiregistry.cms.hhs.gov/api/')
        && $request['version'] === '2.1'
        && $request['number'] === NPI_RULE_VALID);
});

it('accepts an NPI given as an integer', function (): void {
    Http::fake(['npiregistry.cms.hhs.gov/*' => Http::response(['result_count' => 2], 200)]);

    expect(npiRuleMessages(1_234_567_893))->toBe([]);
    Http::assertSent(fn (Request $request): bool => $request['number'] === NPI_RULE_VALID);
});

it('rejects a value that is not exactly ten digits without calling the registry', function (string $value): void {
    Http::fake();

    expect(npiRuleMessages($value))->toBe([__('statamic::validation.npi.format')]);
    Http::assertNothingSent();
})->with([
    'nine digits' => ['123456789'],
    'eleven digits' => ['12345678931'],
    'letter' => ['123456789X'],
    'hyphenated' => ['12345-67893'],
    'leading space' => [' 1234567893'],
    'trailing newline' => ["1234567893\n"],
]);

it('rejects a ten-digit value whose check digit is wrong without calling the registry', function (string $value): void {
    Http::fake();

    expect(npiRuleMessages($value))->toBe([__('statamic::validation.npi.invalid')]);
    Http::assertNothingSent();
})->with([
    'last digit one too low' => ['1234567892'],
    'last digit one too high' => ['1234567894'],
    'all zeros but one' => ['0000000001'],
]);

it('reports a failed registry request', function (int $status): void {
    Http::fake(['npiregistry.cms.hhs.gov/*' => Http::response(['result_count' => 1], $status)]);

    expect(npiRuleMessages(NPI_RULE_VALID))->toBe([__('statamic::validation.npi.api_error')]);
    Http::assertSentCount(1);
})->with([
    'not found' => [404],
    'server error' => [500],
    'unavailable' => [503],
]);

it('reports an NPI the registry has no record of', function (): void {
    Http::fake(['npiregistry.cms.hhs.gov/*' => Http::response(['result_count' => 0, 'results' => []], 200)]);

    expect(npiRuleMessages(NPI_RULE_VALID))->toBe([__('statamic::validation.npi.not_found')]);
});

it('leaves empty input to other rules', function (mixed $value): void {
    Http::fake();

    expect(npiRuleMessages($value))->toBe([]);
    Http::assertNothingSent();
})->with([
    'null' => [null],
    'empty string' => [''],
    'string zero' => ['0'],
]);
