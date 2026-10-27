<?php

declare(strict_types=1);

use App\Rules\IdentifierRegex;
use Tests\TestCase;

uses(TestCase::class);

covers(IdentifierRegex::class);

/**
 * Narrow mutate coverage for App\Rules\IdentifierRegex.
 *
 * @command  php artisan test --compact tests/Unit/Rules/IdentifierRegexMutationTest.php
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

/**
 * Run IdentifierRegex and return whether $fail was invoked.
 */
function identifierRegexFailed(mixed $value, string $pattern = '/^\d{3}$/'): bool
{
    $failed = false;
    $message = null;

    (new IdentifierRegex($pattern, 'Must be exactly 3 digits'))->validate(
        'test',
        $value,
        function (string $msg) use (&$failed, &$message): void {
            $failed = true;
            $message = $msg;
        },
    );

    if ($failed) {
        expect($message)->toBe('Must be exactly 3 digits');
    }

    return $failed;
}

it('skips empty values so required can own emptiness', function (mixed $value): void {
    expect(identifierRegexFailed($value))->toBeFalse();
})->with([
    'null' => [null],
    'empty string' => [''],
    'zero' => [0],
    'false' => [false],
]);

it('accepts matching strings and rejects mismatches', function (mixed $value, bool $shouldPass): void {
    expect(identifierRegexFailed($value))->toBe(! $shouldPass);
})->with([
    'matching string' => ['123', true],
    'mismatch string' => ['12', false],
    'matching array join' => [['1', '2', '3'], true],
    'matching int array join' => [[1, 2, 3], true],
    'mismatch array join' => [['1', '2'], false],
    // Non-scalar array parts coerce to '': join becomes too short.
    'array with junk parts fails' => [['1', new stdClass, '3'], false],
    'integer matching digits' => [123, true],
    'integer mismatch' => [12, false],
    'object coerces to empty and fails' => [new stdClass, false],
]);

it('coerces non-scalars to a true empty string for empty-only patterns', function (mixed $value): void {
    // EmptyStringToNotEmpty on the ``''`` arms would inject a sentinel and fail ``/^$/``.
    expect(identifierRegexFailed($value, '/^$/'))->toBeFalse();
})->with([
    'object value' => [new stdClass],
    'array of objects' => [[new stdClass, new stdClass]],
]);
