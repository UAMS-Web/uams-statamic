<?php

declare(strict_types=1);

use Statamic\Facades\Form as FormFacade;
use Statamic\Fields\Blueprint;
use Statamic\Fields\Field;
use Statamic\Fields\Fields;
use Statamic\Fields\Validator;
use Statamic\Forms\Form;
use Tests\Support\FormsSubmodule;
use Tests\TestCase;

uses(TestCase::class);

/**
 * Server-side `email` validation on the public form blueprints (issue UAMS-Web/uams-statamic-archive#885).
 *
 * Every public form's email field references the shared fieldset field
 * `uamsweb_common_contact.email_address`, which was `type: text` + `input_type: email`
 * — the `input_type` is only a client-side hint, so any string passed server validation.
 * The fix switches that fieldset field to the `email` fieldtype, whose `rules()` returns
 * `['email']`. Statamic merges a fieldtype's `rules()` with each field's configured
 * `validate`, so the rule is additive with the blueprints' `required` overrides.
 *
 * The forms read here live in the private `resources/forms` submodule (UAMS-Web/uams-statamic-archive#2818), so
 * every case skips in a checkout without it.
 *
 * @command php artisan test --compact tests/Feature/Forms/FormEmailValidationTest.php
 */
beforeEach(function (): void {
    FormsSubmodule::skipUnlessInitialized();
});

it('applies a server-side email rule to every public form blueprint', function (string $formHandle, string $fieldHandle): void {
    $form = FormFacade::find($formHandle);

    expect($form)->not->toBeNull("Form {$formHandle} should exist.");
    $form = mustBe(Form::class, $form, 'form');

    /** @var Blueprint $blueprint */
    $blueprint = $form->blueprint();
    /** @var Field $field */
    $field = $blueprint->fields()->get($fieldHandle);

    expect($field)->not->toBeNull("Field {$fieldHandle} should exist on {$formHandle}.");
    expect($field->type())->toBe('email');
    /** @var array<string, mixed> $fieldRules */
    $fieldRules = $field->rules();
    expect($fieldRules[$fieldHandle])->toContain('email');
})->with([
    'innovation_video_assessment_upload_form' => ['innovation_video_assessment_upload_form', 'email_address'],
    'arkansas_traveler' => ['institutional_relations_arkansas_traveler_certificate_request', 'email_address'],
    'proclamation' => ['institutional_relations_proclamation_request', 'email_address'],
    'scheduling' => ['institutional_relations_scheduling_request', 'email_address'],
    'uams_in_your_community' => ['institutional_relations_uams_in_your_community', 'email_address'],
    'mail_processing' => ['mail_processing_center_bulk_mail_request', 'email_address'],
    'spread_kindness' => ['spread_kindness', 'email'],
]);

it('keeps the blueprint required rule alongside the fieldtype email rule', function (): void {
    // A `required` email field gets both rules; the fieldtype rule does not clobber it.
    /** @var Blueprint $requiredBlueprint */
    $requiredBlueprint = FormFacade::find('institutional_relations_scheduling_request')->blueprint();
    /** @var Field $required */
    $required = $requiredBlueprint->fields()->get('email_address');

    /** @var array<string, mixed> $requiredRules */
    $requiredRules = $required->rules();
    expect($requiredRules['email_address'])
        ->toContain('required')
        ->toContain('email');

    // An optional email field is nullable + format-checked (empty allowed, malformed rejected).
    /** @var Blueprint $optionalBlueprint */
    $optionalBlueprint = FormFacade::find('spread_kindness')->blueprint();
    /** @var Field $optional */
    $optional = $optionalBlueprint->fields()->get('email');

    /** @var array<string, mixed> $optionalRulesByField */
    $optionalRulesByField = $optional->rules();
    $optionalRules = $optionalRulesByField['email'];
    expect($optionalRules)->toContain('email');
    expect($optionalRules)->toContain('nullable');
    expect($optionalRules)->not->toContain('required');
});

it('rejects a malformed email and accepts a valid one through the submission validator', function (): void {
    // FrontendFormRequest validates a submission with `$form->blueprint()->fields()->validator()`
    // (Statamic\Http\Requests\FrontendFormRequest::validator), so failing that validator is exactly
    // what rejects a submission. Asserting it directly is deterministic and isolated — no HTTP,
    // no shared submission storage, no notification side effects.
    /** @var Blueprint $blueprint */
    $blueprint = FormFacade::find('spread_kindness')->blueprint();

    /** @var Fields $malformedFields */
    $malformedFields = $blueprint->fields()->addValues(['story' => 'My story', 'email' => 'not-an-email']);
    /** @var Validator $malformedFieldValidator */
    $malformedFieldValidator = $malformedFields->validator();
    /** @var Illuminate\Validation\Validator $malformed */
    $malformed = $malformedFieldValidator->validator();

    expect($malformed->fails())->toBeTrue('A malformed email must fail submission validation.');
    expect($malformed->errors()->has('email'))->toBeTrue();

    /** @var Fields $validFields */
    $validFields = $blueprint->fields()->addValues(['story' => 'My story', 'email' => 'real@example.com']);
    /** @var Validator $validFieldValidator */
    $validFieldValidator = $validFields->validator();
    /** @var Illuminate\Validation\Validator $valid */
    $valid = $validFieldValidator->validator();

    expect($valid->errors()->has('email'))->toBeFalse('A well-formed email must pass submission validation.');
});
