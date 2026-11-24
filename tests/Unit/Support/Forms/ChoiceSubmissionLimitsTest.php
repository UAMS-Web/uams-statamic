<?php

declare(strict_types=1);

use Statamic\Contracts\Forms\Submission;
use Statamic\Fields\Field;
use Statamic\Forms\Form;
use UAMSWeb\Aether\Fieldtypes\Support\Forms\ChoiceSubmissionLimits;

covers(ChoiceSubmissionLimits::class);

/**
 * Unit tests for the config parsing in {@see ChoiceSubmissionLimits::enabledFor()} (the
 * `enable_choice_limits` toggle coercion, including imported string values, and the
 * `choice_limits` grid normalization, which drops malformed rows), and for the submission
 * counting and full-choice paths against a mocked form. Those paths are also covered
 * end-to-end by ChoiceSubmissionLimitsEnforcementTest.
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

it('is enabled when a limit row arrives as a collection', function () {
    expect(choiceLimitsEnabledFor([
        'enable_choice_limits' => true,
        'choice_limits' => [collect(['choice' => 'a', 'limit' => 2])],
    ]))->toBeTrue();
});

it('keeps reading rows after a malformed one', function (mixed $malformed) {
    expect(choiceLimitsEnabledFor([
        'enable_choice_limits' => true,
        'choice_limits' => [$malformed, ['choice' => 'b', 'limit' => 5]],
    ]))->toBeTrue();
})->with([
    'non-array row' => ['not-an-array'],
    'non-scalar choice' => [['choice' => ['x'], 'limit' => 2]],
    'non-numeric limit' => [['choice' => 'a', 'limit' => 'lots']],
]);

it('caps a choice at a limit of one', function () {
    expect(choiceLimitsEnabledFor(['enable_choice_limits' => true, 'choice_limits' => [['choice' => 'a', 'limit' => 1]]]))
        ->toBeTrue();
});

it('drops a limit that truncates to zero', function () {
    expect(choiceLimitsEnabledFor(['enable_choice_limits' => true, 'choice_limits' => [['choice' => 'a', 'limit' => '0.5']]]))
        ->toBeFalse();
});

it('ignores an object of limit rows that is not iterable', function () {
    $rows = new stdClass;
    $rows->row = ['choice' => 'a', 'limit' => 2];

    expect(choiceLimitsEnabledFor(['enable_choice_limits' => true, 'choice_limits' => $rows]))->toBeFalse();
});

it('matches toggle strings exactly after trimming them', function (string $toggle, bool $enabled) {
    expect(choiceLimitsEnabledFor(['enable_choice_limits' => $toggle, 'choice_limits' => validLimitRows()]))
        ->toBe($enabled);
})->with([
    'padded yes' => [' yes ', true],
    'leading zero' => ['01', false],
    'decimal one' => ['1.0', false],
]);

/**
 * A form whose submissions are the given items.
 *
 * @param  list<mixed>  $items  Stored submissions, or other values the collection may hold.
 */
function choiceLimitsForm(array $items): Form
{
    $form = Mockery::mock(Form::class);
    $form->shouldReceive('submissions')->andReturn(collect($items));

    return $form;
}

/**
 * A stored submission whose `slot` field holds the given value.
 */
function choiceLimitsSubmission(mixed $value): Submission
{
    $submission = Mockery::mock(Submission::class);
    $submission->shouldReceive('get')->with('slot')->andReturn($value);

    return $submission;
}

/**
 * A checkboxes field, `slot` by default, carrying the given limit rows.
 *
 * @param  list<array<string, mixed>>  $rows
 */
function choiceLimitsField(array $rows, mixed $enabled = true, ?string $handle = 'slot'): Field
{
    return new Field($handle, [
        'type' => 'checkboxes',
        'options' => ['a' => 'A', 'b' => 'B'],
        'enable_choice_limits' => $enabled,
        'choice_limits' => $rows,
    ]);
}

it('reports a choice full once its count meets its cap, and not before', function () {
    expect((new ChoiceSubmissionLimits)->fullChoices(
        choiceLimitsForm([choiceLimitsSubmission('a'), choiceLimitsSubmission(['a', 'b'])]),
        choiceLimitsField([['choice' => 'a', 'limit' => 2], ['choice' => 'b', 'limit' => 2]]),
    ))->toBe(['a']);
});

it('reports no full choice when no submission selected it', function () {
    expect((new ChoiceSubmissionLimits)->fullChoices(
        choiceLimitsForm([choiceLimitsSubmission('b')]),
        choiceLimitsField([['choice' => 'a', 'limit' => 1]]),
    ))->toBe([]);
});

it('reports no full choice when limits are disabled, or the field has no handle', function (mixed $enabled, ?string $handle) {
    expect((new ChoiceSubmissionLimits)->fullChoices(
        choiceLimitsForm([choiceLimitsSubmission('a')]),
        choiceLimitsField([['choice' => 'a', 'limit' => 1]], $enabled, $handle),
    ))->toBe([]);
})->with([
    'disabled' => [false, 'slot'],
    'no handle' => [true, null],
]);

it('reports no full choice when no limit is configured', function () {
    expect((new ChoiceSubmissionLimits)->fullChoices(
        choiceLimitsForm([choiceLimitsSubmission('a')]),
        choiceLimitsField([]),
    ))->toBe([]);
});

it('counts only stored submissions, including those after anything else', function () {
    expect((new ChoiceSubmissionLimits)->fullChoices(
        choiceLimitsForm([collect(['slot' => 'a']), choiceLimitsSubmission('a')]),
        choiceLimitsField([['choice' => 'a', 'limit' => 1], ['choice' => 'b', 'limit' => 1]]),
    ))->toBe(['a']);
});

it('ignores a stored value that is not a submission', function () {
    expect((new ChoiceSubmissionLimits)->fullChoices(
        choiceLimitsForm([collect(['slot' => 'a'])]),
        choiceLimitsField([['choice' => 'a', 'limit' => 1]]),
    ))->toBe([]);
});

it('counts the scalar selections after a non-scalar one', function () {
    expect((new ChoiceSubmissionLimits)->fullChoices(
        choiceLimitsForm([choiceLimitsSubmission([['x'], 'a'])]),
        choiceLimitsField([['choice' => 'a', 'limit' => 1]]),
    ))->toBe(['a']);
});

it('counts a stored number as the matching option value', function () {
    expect((new ChoiceSubmissionLimits)->fullChoices(
        choiceLimitsForm([choiceLimitsSubmission(1.5)]),
        choiceLimitsField([['choice' => 1.5, 'limit' => 1]]),
    ))->toBe(['1.5']);
});

it('truncates a fractional limit to its whole number', function () {
    expect((new ChoiceSubmissionLimits)->fullChoices(
        choiceLimitsForm([choiceLimitsSubmission('a'), choiceLimitsSubmission('a')]),
        choiceLimitsField([['choice' => 'a', 'limit' => '2.5']]),
    ))->toBe(['a']);
});

it('says whether a selection hits a full choice', function (mixed $submitted, bool $hits) {
    expect((new ChoiceSubmissionLimits)->selectionHitsFullChoice(
        choiceLimitsForm([choiceLimitsSubmission('a')]),
        choiceLimitsField([['choice' => 'a', 'limit' => 1], ['choice' => 'b', 'limit' => 1]]),
        $submitted,
    ))->toBe($hits);
})->with([
    'the full choice' => ['a', true],
    'the full choice among others' => [['b', 'a'], true],
    'an open choice' => ['b', false],
    'a non-scalar selection' => [[['a']], false],
]);

it('matches a numeric selection against a full numeric option', function () {
    expect((new ChoiceSubmissionLimits)->selectionHitsFullChoice(
        choiceLimitsForm([choiceLimitsSubmission('1')]),
        choiceLimitsField([['choice' => '1', 'limit' => 1]]),
        1,
    ))->toBeTrue();
});

it('lets any selection through when no choice is full', function () {
    expect((new ChoiceSubmissionLimits)->selectionHitsFullChoice(
        choiceLimitsForm([]),
        choiceLimitsField([['choice' => 'a', 'limit' => 1]]),
        'a',
    ))->toBeFalse();
});
