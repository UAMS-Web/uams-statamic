<?php

declare(strict_types=1);

use App\Rules\AsciiIdentifier;
use Tests\TestCase;

uses(TestCase::class);

covers(AsciiIdentifier::class);

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
 * Behavior of the App\Rules\AsciiIdentifier validation rule: accept ASCII
 * identifier handles (letters/underscore start, letters/digits/underscore after;
 * case-insensitive) and reject anything else — leading digits, hyphens, `$`,
 * unicode, spaces, dots, backslashes, quotes — plus non-string values. Empty/null
 * skip so optionality is handled elsewhere. The registered `ascii_identifier`
 * string alias mirrors the object rule.
 *
 * @command  php artisan test --compact tests/Feature/Rules/AsciiIdentifierRuleTest.php
 */
it('accepts valid ASCII identifiers and rejects everything else', function (mixed $handle, bool $shouldPass) {
    $validator = validator(['handle' => $handle], ['handle' => [new AsciiIdentifier]]);

    expect($validator->passes())->toBe($shouldPass);
})->with([
    'empty string skips' => ['', true],
    'null skips' => [null, true],
    'simple lowercase' => ['weight', true],
    'snake_case' => ['weight_kg', true],
    'trailing digits' => ['h2o_volume', true],
    'leading underscore' => ['_internal', true],
    'mixed case' => ['CamelCaseHandle', true],
    'single underscore' => ['_', true],
    'leading digit' => ['1kg', false],
    'hyphen' => ['weight-kg', false],
    'dollar sign' => ['$weight', false],
    'unicode letter' => ['café', false],
    'space' => ['weight kg', false],
    'dot' => ['weight.kg', false],
    'backslash' => ['weight\\kg', false],
    'single quote' => ["weight'kg", false],
    'trailing newline' => ["weight_kg\n", false],
    'trailing crlf' => ["weight_kg\r\n", false],
    'trailing carriage return' => ["weight_kg\r", false],
    'array value' => [['weight_kg'], false],
    'integer (leading digit)' => [1, false],
    'float' => [1.5, false],
    'boolean true' => [true, false],
    'boolean false skips' => [false, true],
    'zero skips' => [0, true],
]);

it('fails an array value with a typed invalid_type message', function () {
    $validator = validator(['handle' => ['weight_kg']], ['handle' => [new AsciiIdentifier]]);

    expect($validator->passes())->toBeFalse();
    expect($validator->errors()->first('handle'))->toBe(
        __('statamic::validation.ascii_identifier.invalid_type', ['attribute' => 'handle']),
    );
});

it('names the constraint in the pattern-mismatch message', function () {
    $validator = validator(['handle' => 'weight-kg'], ['handle' => [new AsciiIdentifier]]);

    expect($validator->passes())->toBeFalse();
    expect($validator->errors()->first('handle'))->toBe(
        __('statamic::validation.ascii_identifier.invalid_format', ['attribute' => 'handle']),
    );
});

it('mirrors the object rule through the registered string alias', function (string $handle, bool $shouldPass) {
    $validator = validator(['handle' => $handle], ['handle' => ['ascii_identifier']]);

    expect($validator->passes())->toBe($shouldPass);
})->with([
    'rejects hyphens' => ['weight-kg', false],
    'accepts a valid identifier' => ['weight_kg', true],
]);
