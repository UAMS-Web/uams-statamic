<?php

declare(strict_types=1);

use App\Listeners\EnforceChoiceSubmissionLimits;
use Illuminate\Validation\ValidationException;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Statamic\Events\FormSubmitted;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Forms\Form;
use Statamic\Forms\Submission;
use Tests\TestCase;

uses(TestCase::class, MockeryPHPUnitIntegration::class);

/**
 * @covers \App\Listeners\EnforceChoiceSubmissionLimits
 *
 * The listener's own guards, with the form, blueprint and submission mocked. Rejecting a full
 * choice end to end, through a real POST and a real submission count, is
 * tests/Feature/Forms/ChoiceSubmissionLimitsEnforcementTest.php.
 *
 * Each skipped field is asserted by the submission never being read for it: the listener reads
 * the submitted value only for a field it goes on to check.
 *
 * @command  php artisan test --compact tests/Feature/Listeners/EnforceChoiceSubmissionLimitsTest.php
 */

/**
 * A submission whose form has a blueprint holding the given field list.
 *
 * @param  array<array-key, mixed>  $fields  What the blueprint's `fields()->all()` returns.
 */
function choiceLimitSubmission(array $fields): Submission&MockInterface
{
    $fieldList = Mockery::mock(Fields::class);
    $fieldList->shouldReceive('all')->andReturn(collect($fields));

    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('fields')->andReturn($fieldList);

    $form = Mockery::mock(Form::class);
    $form->shouldReceive('blueprint')->andReturn($blueprint);

    $submission = Mockery::mock(Submission::class);
    $submission->shouldReceive('form')->andReturn($form);

    return $submission;
}

/**
 * Fire the listener for a submission.
 */
function choiceLimitFire(Submission $submission): void
{
    (new EnforceChoiceSubmissionLimits)->handle(new FormSubmitted($submission));
}

it('lets a submission through when it has no form', function (): void {
    $submission = Mockery::mock(Submission::class);
    $submission->shouldReceive('form')->once()->andReturn(null);
    $submission->shouldReceive('get')->never();

    choiceLimitFire($submission);
});

it('lets a submission through when its form has no blueprint', function (): void {
    $form = Mockery::mock(Form::class);
    $form->shouldReceive('blueprint')->once()->andReturn(null);

    $submission = Mockery::mock(Submission::class);
    $submission->shouldReceive('form')->andReturn($form);
    $submission->shouldReceive('get')->never();

    choiceLimitFire($submission);
});

it('skips an entry in the field list that is not a field', function (): void {
    $submission = choiceLimitSubmission(['slot' => 'not a field']);
    $submission->shouldReceive('get')->never();

    choiceLimitFire($submission);
});

it('skips a field that has no choice limits', function (array $config): void {
    $submission = choiceLimitSubmission(['slot' => new Field('slot', ['type' => 'select'] + $config)]);
    $submission->shouldReceive('get')->never();

    choiceLimitFire($submission);
})->with([
    'limits not enabled' => [['choice_limits' => [['choice' => 'morning', 'limit' => 1]]]],
    'enabled with no limits' => [['enable_choice_limits' => true, 'choice_limits' => []]],
]);

it('skips a limited field that has no handle', function (): void {
    $submission = choiceLimitSubmission([new Field(null, [
        'type' => 'select',
        'enable_choice_limits' => true,
        'choice_limits' => [['choice' => 'morning', 'limit' => 1]],
    ])]);
    $submission->shouldReceive('get')->never();

    choiceLimitFire($submission);
});

/**
 * A submission selecting `morning` for a `slot` field capped at one `morning` submission, on a
 * form that already holds one: the selection hits a full choice. Any fields given go before
 * `slot` in the blueprint.
 *
 * @param  array<array-key, mixed>  $fieldsBefore  Field-list entries ahead of the limited field.
 * @param  array<string, mixed>  $slotConfig  Extra config for the limited field.
 */
function choiceLimitFullSubmission(array $fieldsBefore = [], array $slotConfig = []): Submission&MockInterface
{
    $slot = new Field('slot', [
        'type' => 'select',
        'options' => ['morning' => 'Morning', 'evening' => 'Evening'],
        'enable_choice_limits' => true,
        'choice_limits' => [['choice' => 'morning', 'limit' => 1]],
    ] + $slotConfig);

    $earlier = Mockery::mock(Submission::class);
    $earlier->shouldReceive('get')->with('slot')->andReturn('morning');

    $fieldList = Mockery::mock(Fields::class);
    $fieldList->shouldReceive('all')->andReturn(collect([...$fieldsBefore, 'slot' => $slot]));

    $blueprint = Mockery::mock(Blueprint::class);
    $blueprint->shouldReceive('fields')->andReturn($fieldList);

    $form = Mockery::mock(Form::class);
    $form->shouldReceive('blueprint')->andReturn($blueprint);
    $form->shouldReceive('submissions')->andReturn(collect([$earlier]));

    $submission = Mockery::mock(Submission::class);
    $submission->shouldReceive('form')->andReturn($form);
    $submission->shouldReceive('get')->with('slot')->andReturn('morning');

    return $submission;
}

/**
 * The validation errors the listener throws for a submission, or null when it lets it through.
 *
 * @return array<array-key, mixed>|null
 */
function choiceLimitErrors(Submission $submission): ?array
{
    try {
        choiceLimitFire($submission);
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return null;
}

it('rejects a selection of a full choice, keyed by the field', function (): void {
    expect(choiceLimitErrors(choiceLimitFullSubmission()))->toBe([
        'slot' => ['One or more of your selections is no longer available. Please choose another option.'],
    ]);
});

it('still checks the fields after one it skips', function (array $skipped): void {
    // Skipping a field moves on to the next one; it does not stop the check.
    expect(choiceLimitErrors(choiceLimitFullSubmission($skipped)))->toHaveKey('slot');
})->with([
    'not a field' => [['notes' => 'not a field']],
    'a field with no limits' => [['notes' => new Field('notes', ['type' => 'text'])]],
    'a limited field with no handle' => [[new Field(null, [
        'type' => 'select',
        'enable_choice_limits' => true,
        'choice_limits' => [['choice' => 'morning', 'limit' => 1]],
    ])]],
]);

it('uses the field\'s own message, trimmed', function (mixed $message, string $expected): void {
    expect(choiceLimitErrors(choiceLimitFullSubmission(slotConfig: ['choice_limit_message' => $message])))
        ->toBe(['slot' => [$expected]]);
})->with([
    'padded text' => ['  That slot is full.  ', 'That slot is full.'],
    'a number' => [42, '42'],
]);

it('falls back to the default message when the field\'s own is blank or not text', function (mixed $message): void {
    expect(choiceLimitErrors(choiceLimitFullSubmission(slotConfig: ['choice_limit_message' => $message])))
        ->toBe(['slot' => ['One or more of your selections is no longer available. Please choose another option.']]);
})->with([
    'empty' => [''],
    'whitespace' => ['   '],
    'a list' => [['That slot is full.']],
]);
