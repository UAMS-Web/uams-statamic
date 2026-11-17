<?php

declare(strict_types=1);

use App\Fieldtypes\Radio;
use App\Fieldtypes\Select;
use Statamic\Contracts\Forms\Submission;
use Statamic\Facades\Form as FormFacade;
use Statamic\Fields\Field;
use Statamic\Forms\Form;
use Tests\TestCase;

uses(TestCase::class);

covers(Select::class, Radio::class);

/**
 * The renderable field data the Select and Radio fieldtypes hand their public-form
 * templates: Statamic's option map, plus the option values that have reached their
 * per-choice submission limit, which the templates disable
 * (UAMS-Web/uams-statamic-archive#292, UAMS-Web/uams-statamic#312).
 *
 * @command  php artisan test --compact tests/Feature/Fieldtypes/ChoiceFieldtypeRenderDataTest.php
 */

/**
 * A choice field of the given type capping "morning" at one submission, attached
 * to a form whose stored submissions are the given slot values.
 *
 * @param  list<string>  $slots
 */
function choiceRenderDataField(string $type, array $slots): Field
{
    $real = FormFacade::make('choice_render_data_probe');
    $submissions = collect($slots)->map(function (string $slot) use ($real): Submission {
        $submission = $real->makeSubmission();
        $submission->data(['slot' => $slot]);

        return $submission;
    });

    $form = Mockery::mock(Form::class)->makePartial();
    $form->shouldReceive('submissions')->andReturn($submissions);

    $field = new Field('slot', [
        'type' => $type,
        'options' => ['morning' => 'Morning', 'evening' => 'Evening'],
        'enable_choice_limits' => true,
        'choice_limits' => [['choice' => 'morning', 'limit' => 1]],
    ]);
    $field->setForm($form);

    return $field;
}

/**
 * The application fieldtype a choice field of the given type resolves to.
 *
 * @param  list<string>  $slots
 */
function choiceRenderDataFieldtype(string $type, array $slots): Select|Radio
{
    $fieldtype = choiceRenderDataField($type, $slots)->fieldtype();

    if (! $fieldtype instanceof Select && ! $fieldtype instanceof Radio) {
        throw new UnexpectedValueException("The {$type} handle did not resolve to an application choice fieldtype.");
    }

    return $fieldtype;
}

it("resolves the :dataset handle to the application fieldtype, keeping Statamic's icon", function (string $type, string $class, string $icon): void {
    $fieldtype = choiceRenderDataFieldtype($type, []);

    expect($fieldtype::class)->toBe($class)
        ->and($fieldtype->icon())->toBe($icon);
})->with([
    'select' => ['select', Select::class, 'fieldtype-select'],
    'radio' => ['radio', Radio::class, 'fieldtype-radio'],
]);

it('keeps the option map and lists the full choices for :dataset', function (string $type): void {
    expect(choiceRenderDataFieldtype($type, ['morning', 'evening'])->extraRenderableFieldData())->toBe([
        'options' => ['morning' => 'Morning', 'evening' => 'Evening'],
        'full_choices' => ['morning'],
    ]);
})->with(['select', 'radio']);

it('lists no full choice for :dataset while every limit has room', function (string $type): void {
    expect(choiceRenderDataFieldtype($type, ['evening'])->extraRenderableFieldData())->toBe([
        'options' => ['morning' => 'Morning', 'evening' => 'Evening'],
        'full_choices' => [],
    ]);
})->with(['select', 'radio']);
