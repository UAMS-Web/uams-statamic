<?php

declare(strict_types=1);

use App\Support\Forms\ChoiceSubmissionLimits;
use Statamic\Fields\Field;

/**
 * Unit tests for the config parsing in {@see ChoiceSubmissionLimits::enabledFor()} — the
 * `enable_choice_limits` toggle coercion (including imported string values) and the
 * `choice_limits` grid normalization (dropping malformed rows). The submission-counting and
 * full-choice paths are covered end-to-end by ChoiceSubmissionLimitsEnforcementTest.
 *
 * @command php artisan test --compact tests/Unit/Support/Forms/ChoiceSubmissionLimitsTest.php
 */

/**
 * Whether a field with the given config opts into per-choice limits.
 *
 * @param  array<string, mixed>  $config
 */
function choiceLimitsEnabledFor(array $config): bool
{
    return (new ChoiceSubmissionLimits)->enabledFor(new Field('slot', [
        'type' => 'select',
        'options' => ['a' => 'A', 'b' => 'B'],
        ...$config,
    ]));
}

/**
 * A valid single-row limit config, reused across the toggle-coercion cases.
 *
 * @return list<array{choice: string, limit: int}>
 */
function validLimitRows(): array
{
    return [['choice' => 'a', 'limit' => 2]];
}

it('treats the boolean and imported-string truthy toggle values as enabled', function (mixed $toggle) {
    expect(choiceLimitsEnabledFor(['enable_choice_limits' => $toggle, 'choice_limits' => validLimitRows()]))
        ->toBeTrue();
})->with([
    'bool true' => true,
    'string true' => 'true',
    'string TRUE' => 'TRUE',
    'string 1' => '1',
    'string yes' => 'yes',
    'string on' => 'on',
    'int 1' => 1,
]);

it('treats falsy and unrecognized toggle values as disabled', function (mixed $toggle) {
    expect(choiceLimitsEnabledFor(['enable_choice_limits' => $toggle, 'choice_limits' => validLimitRows()]))
        ->toBeFalse();
})->with([
    'bool false' => false,
    'string false' => 'false',
    'string no' => 'no',
    'empty string' => '',
    'int 0' => 0,
    'null' => null,
    'unrecognized string' => 'perhaps',
]);

it('is disabled when the toggle key is absent entirely', function () {
    expect((new ChoiceSubmissionLimits)->enabledFor(new Field('slot', [
        'type' => 'select',
        'options' => ['a' => 'A'],
        'choice_limits' => validLimitRows(),
    ])))->toBeFalse();
});

it('is disabled when enabled but every limit row is malformed', function (array $rows) {
    expect(choiceLimitsEnabledFor(['enable_choice_limits' => true, 'choice_limits' => $rows]))
        ->toBeFalse();
})->with([
    'no rows' => [[]],
    'non-array row' => [['not-an-array']],
    'missing choice' => [[['limit' => 2]]],
    'non-scalar choice' => [[['choice' => ['x'], 'limit' => 2]]],
    'non-numeric limit' => [[['choice' => 'a', 'limit' => 'lots']]],
    'zero limit' => [[['choice' => 'a', 'limit' => 0]]],
    'negative limit' => [[['choice' => 'a', 'limit' => -3]]],
]);

it('is disabled when choice_limits is not iterable', function () {
    expect(choiceLimitsEnabledFor(['enable_choice_limits' => true, 'choice_limits' => 'nonsense']))
        ->toBeFalse();
});

it('is enabled when at least one valid limit row survives alongside malformed ones', function () {
    expect(choiceLimitsEnabledFor([
        'enable_choice_limits' => true,
        'choice_limits' => [
            ['choice' => 'a', 'limit' => 0],       // dropped
            ['choice' => 'b', 'limit' => 5],       // valid
        ],
    ]))->toBeTrue();
});
