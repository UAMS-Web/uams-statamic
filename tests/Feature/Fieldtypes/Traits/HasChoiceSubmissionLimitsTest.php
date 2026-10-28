<?php

declare(strict_types=1);

use App\Fieldtypes\Traits\HasChoiceSubmissionLimits;
use Statamic\Contracts\Forms\Submission;
use Statamic\Facades\Form as FormFacade;
use Statamic\Fields\Field;
use Statamic\Forms\Form;
use Tests\Support\Doubles\ChoiceSubmissionLimitsHarness;
use Tests\TestCase;

covers(HasChoiceSubmissionLimits::class);

uses(TestCase::class);

/**
 * Behavior of the HasChoiceSubmissionLimits trait (app/Fieldtypes/Traits/HasChoiceSubmissionLimits.php):
 * the per-choice limit config fields, and the full-choice list a choice field
 * renders with, which is empty when the fieldtype has no field or the field has
 * no form. How the limits themselves are counted belongs to
 * ChoiceSubmissionLimitsTest and ChoiceSubmissionLimitsEnforcementTest.
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/Traits/HasChoiceSubmissionLimitsTest.php
 */
function choiceLimitsHarness(): ChoiceSubmissionLimitsHarness
{
    return new ChoiceSubmissionLimitsHarness;
}

/**
 * A select field capping "morning" at one submission, attached to a form whose
 * stored submissions are the given slot values.
 *
 * @param  list<string>  $slots
 */
function choiceLimitedFieldWithSubmissions(array $slots): Field
{
    $real = FormFacade::make('choice_limits_trait_probe');
    $submissions = collect($slots)->map(function (string $slot) use ($real): Submission {
        $submission = $real->makeSubmission();
        $submission->data(['slot' => $slot]);

        return $submission;
    });

    $form = Mockery::mock(Form::class)->makePartial();
    $form->shouldReceive('submissions')->andReturn($submissions);

    $field = new Field('slot', [
        'type' => 'select',
        'options' => ['morning' => 'Morning', 'evening' => 'Evening'],
        'enable_choice_limits' => true,
        'choice_limits' => [['choice' => 'morning', 'limit' => 1]],
    ]);
    $field->setForm($form);

    return $field;
}

it('builds the limit toggle, the per-choice grid and the full-choice message', function (): void {
    expect(choiceLimitsHarness()->limitFields())->toBe([
        'enable_choice_limits' => [
            'display' => __('Limit Submissions Per Choice'),
            'instructions' => __('Cap how many submissions may select each choice. A choice becomes unavailable once its limit is reached.'),
            'type' => 'toggle',
            'default' => false,
        ],
        'choice_limits' => [
            'display' => __('Per-Choice Limits'),
            'instructions' => __('Add a row per capped choice. The "Choice Value" is the option key; leave a choice out to keep it unlimited.'),
            'type' => 'grid',
            'mode' => 'stacked',
            'add_row' => __('Add Limit'),
            'if' => ['enable_choice_limits' => 'equals true'],
            'fields' => [
                [
                    'handle' => 'choice',
                    'field' => [
                        'type' => 'text',
                        'display' => __('Choice Value'),
                        'instructions' => __('The option value (key) to cap.'),
                        'validate' => ['required'],
                    ],
                ],
                [
                    'handle' => 'limit',
                    'field' => [
                        'type' => 'integer',
                        'display' => __('Limit'),
                        'min' => 1,
                        'validate' => ['required'],
                    ],
                ],
            ],
        ],
        'choice_limit_message' => [
            'display' => __('Full-Choice Message'),
            'instructions' => __('Shown when a submission selects a choice that has reached its limit.'),
            'type' => 'text',
            'default' => 'One or more of your selections is no longer available. Please choose another option.',
            'if' => ['enable_choice_limits' => 'equals true'],
        ],
    ]);
});

it('lists the choices that have reached their limit on the field\'s form', function (): void {
    $harness = choiceLimitsHarness();
    $harness->setField(choiceLimitedFieldWithSubmissions(['morning', 'evening']));

    expect($harness->full())->toBe(['morning']);
});

it('lists no full choice while every limit has room', function (): void {
    $harness = choiceLimitsHarness();
    $harness->setField(choiceLimitedFieldWithSubmissions(['evening']));

    expect($harness->full())->toBe([]);
});

it('lists no full choice when the field has no form', function (): void {
    $harness = choiceLimitsHarness();
    $harness->setField(new Field('slot', [
        'type' => 'select',
        'enable_choice_limits' => true,
        'choice_limits' => [['choice' => 'morning', 'limit' => 1]],
    ]));

    expect($harness->full())->toBe([]);
});

it('lists no full choice when the fieldtype has no field', function (): void {
    expect(choiceLimitsHarness()->full())->toBe([]);
});
